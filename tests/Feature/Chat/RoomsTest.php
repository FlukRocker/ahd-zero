<?php

namespace Tests\Feature\Chat;

use App\Broadcasting\RosterChannel;
use App\Contracts\ChatHistory;
use App\Enums\ChatRank;
use App\Events\Chat\MemberSanctioned;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageSent;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Member;
use App\Services\Chat\ChatService;
use App\Services\Chat\ModerationService;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RoomsTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    private ChatRoom $anime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->member = $this->makeMember('Rin');
        $this->anime = ChatRoom::create(['slug' => 'anime', 'name' => 'Anime talk', 'topic' => 'Seasonal', 'sort' => 10, 'active' => true]);
    }

    private function makeMember(string $name, ChatRank $rank = ChatRank::Member): Member
    {
        $member = Member::create([
            'name' => $name,
            'email' => strtolower($name).'@example.test',
            'password' => 'password123!',
            'email_verified_at' => now(),
        ]);

        ChatMember::create(['member_id' => $member->id, 'rank' => $rank]);

        return $member;
    }

    /** @return array<string, mixed> */
    private function bootstrap(string $html): array
    {
        preg_match('#<script type="application/json" id="chat-bootstrap">(.*?)</script>#s', $html, $m);

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_show_lobby_and_other_room(): void
    {
        $lobby = $this->bootstrap($this->get('/chat')->assertOk()->getContent());
        $this->assertSame('lobby', $lobby['room']['slug']);

        $other = $this->bootstrap($this->get('/chat/room/anime')->assertOk()->getContent());
        $this->assertSame('anime', $other['room']['slug']);
        $this->assertSame($this->anime->id, $other['room']['id']);
        $this->assertContains('anime', array_column($other['rooms'], 'slug'));
    }

    public function test_inactive_or_unknown_room_page_is_404(): void
    {
        ChatRoom::create(['slug' => 'closed', 'name' => 'Closed', 'active' => false]);

        $this->get('/chat/room/closed')->assertNotFound();
        $this->get('/chat/room/nope')->assertNotFound();
    }

    public function test_post_to_inactive_or_unknown_room_is_refused(): void
    {
        $closed = ChatRoom::create(['slug' => 'closed', 'name' => 'Closed', 'active' => false]);

        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'hi', 'room_id' => $closed->id])
            ->assertStatus(422);
        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'hi', 'room_id' => 99999])
            ->assertStatus(422);

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_post_without_room_id_goes_to_the_lobby(): void
    {
        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'old page'])
            ->assertCreated();

        $this->assertSame(ChatRoom::lobby()->id, (int) ChatMessage::query()->firstOrFail()->room_id);
    }

    public function test_message_is_broadcast_on_its_exact_room_channel(): void
    {
        Event::fake([MessageSent::class]);

        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'hi', 'room_id' => $this->anime->id])
            ->assertCreated();

        Event::assertDispatched(
            MessageSent::class,
            fn (MessageSent $e): bool => $e->broadcastOn()->name === 'chat.room.'.$this->anime->id,
        );
    }

    public function test_history_is_per_room(): void
    {
        $lobbyMsg = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'in lobby']);
        ChatMessage::create(['member_id' => $this->member->id, 'body' => 'in anime', 'room_id' => $this->anime->id]);

        $service = $this->app->make(ChatService::class);

        $this->assertSame(['in lobby'], array_column($service->history(ChatRoom::lobby()->id), 'body'));
        $this->assertSame(['in anime'], array_column($service->history($this->anime->id), 'body'));
        $this->assertSame($lobbyMsg->fresh()->room_id, ChatRoom::lobby()->id);
    }

    public function test_delete_only_clears_own_room_history_and_broadcasts_on_it(): void
    {
        Event::fake([MessageDeleted::class]);
        $lobbyId = ChatRoom::lobby()->id;
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'oops', 'room_id' => $this->anime->id]);

        $history = app(ChatHistory::class);
        $history->replace($lobbyId, [['id' => 1, 'body' => 'keep']]);
        $history->replace($this->anime->id, [['id' => $message->id, 'body' => 'oops']]);

        $this->actingAs($this->member, 'member')->deleteJson('/chat/messages/'.$message->id)->assertOk();

        $this->assertNull($history->recent($this->anime->id, 10));
        $this->assertNotNull($history->recent($lobbyId, 10));
        Event::assertDispatched(
            MessageDeleted::class,
            fn (MessageDeleted $e): bool => $e->broadcastOn()->name === 'chat.room.'.$this->anime->id && $e->messageId === $message->id,
        );
    }

    public function test_sanction_is_broadcast_on_every_active_room_only(): void
    {
        Event::fake([MemberSanctioned::class]);
        ChatRoom::create(['slug' => 'closed', 'name' => 'Closed', 'active' => false]);
        $target = ChatMember::query()->where('member_id', $this->member->id)->firstOrFail();

        $this->app->make(ModerationService::class)->gag($target, 10, null, null);

        Event::assertDispatchedTimes(MemberSanctioned::class, 1);
        Event::assertDispatched(MemberSanctioned::class, function (MemberSanctioned $e): bool {
            $names = array_map(fn ($c): string => $c->name, $e->broadcastOn());
            sort($names);

            return $names === ['chat.room.'.ChatRoom::lobby()->id, 'chat.room.'.$this->anime->id];
        });
    }

    public function test_roster_channel_rejects_inactive_room_and_banned_member(): void
    {
        $closed = ChatRoom::create(['slug' => 'closed', 'name' => 'Closed', 'active' => false]);

        $this->assertIsArray((new RosterChannel)->join($this->member, $this->anime));
        $this->assertFalse((new RosterChannel)->join($this->member, $closed));

        ChatMember::query()->where('member_id', $this->member->id)->update(['banned_at' => now()]);
        $this->assertFalse((new RosterChannel)->join($this->member->fresh(), $this->anime));
    }

    public function test_presence_auth_endpoint_per_room(): void
    {
        // The null broadcaster never invokes channel callbacks, so switch to a
        // real driver with a fake key and re-register the channels on it. A 403
        // on the ACTIVE room means the channel was not re-registered.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'k',
            'broadcasting.connections.reverb.secret' => 's',
            'broadcasting.connections.reverb.app_id' => '1',
            'broadcasting.connections.reverb.options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ]);
        $this->app->make(BroadcastManager::class)->purge();
        require base_path('routes/channels.php');

        $closed = ChatRoom::create(['slug' => 'closed', 'name' => 'Closed', 'active' => false]);
        $post = fn (int $roomId) => $this->actingAs($this->member, 'member')->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-chat.roster.'.$roomId,
        ]);

        $post($this->anime->id)->assertOk();
        $post($closed->id)->assertForbidden();

        ChatMember::query()->where('member_id', $this->member->id)->update(['banned_at' => now()]);
        $post($this->anime->id)->assertForbidden();
    }
}
