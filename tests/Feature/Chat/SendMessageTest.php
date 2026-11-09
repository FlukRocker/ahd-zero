<?php

namespace Tests\Feature\Chat;

use App\Contracts\ChatHistory;
use App\Events\Chat\MessageSent;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\Member;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->member = Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
            'email_verified_at' => now(),
        ]);
    }

    public function test_a_guest_may_read_the_lobby(): void
    {
        $this->get('/chat')->assertOk();
    }

    public function test_a_guest_may_not_post(): void
    {
        $this->postJson('/chat/messages', ['body' => 'hello'])->assertUnauthorized();
    }

    public function test_an_unverified_member_can_post(): void
    {
        $unverified = Member::create([
            'name' => 'Nobu',
            'email' => 'nobu@example.test',
            'password' => 'password123!',
        ]);

        $this->actingAs($unverified, 'member')
            ->postJson('/chat/messages', ['body' => 'hello'])
            ->assertSuccessful();
    }

    public function test_a_verified_member_can_post_and_it_broadcasts(): void
    {
        Event::fake([MessageSent::class]);

        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'hello room'])
            ->assertCreated();

        $this->assertDatabaseHas('chat_messages', [
            'member_id' => $this->member->id,
            'body' => 'hello room',
        ]);
        Event::assertDispatched(MessageSent::class, function (MessageSent $event): bool {
            $keys = array_keys($event->broadcastWith());
            sort($keys);

            return $event->broadcastOn()->name === 'chat.room.'.\App\Models\ChatRoom::lobby()->id
                && $keys === ['at', 'body', 'id', 'name', 'parts', 'rank', 'style', 'uuid'];
        });
    }

    public function test_record_rank_is_the_authors_current_rank(): void
    {
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hi']);
        $chat = $this->app->make(\App\Services\Chat\ChatService::class);

        $this->assertSame('member', $chat->record($message)['rank']);

        ChatMember::updateOrCreate(['member_id' => $this->member->id], ['rank' => \App\Enums\ChatRank::Owner]);

        $fresh = $this->app->make(\App\Services\Chat\ChatService::class);
        $this->assertSame('owner', $fresh->record($message->fresh())['rank']);
    }

    public function test_a_gagged_member_is_refused(): void
    {
        ChatMember::create([
            'member_id' => $this->member->id,
            'gagged_until' => now()->addMinutes(10),
        ]);

        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'hello'])
            ->assertForbidden();

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_a_kicked_member_is_refused_for_the_cooldown(): void
    {
        ChatMember::create([
            'member_id' => $this->member->id,
            'kicked_until' => now()->addSeconds(60),
        ]);

        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'hello'])
            ->assertForbidden();
    }

    public function test_a_banned_member_is_refused(): void
    {
        ChatMember::create(['member_id' => $this->member->id, 'banned_at' => now()]);

        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'hello'])
            ->assertForbidden();
    }

    public function test_the_rate_limiter_stops_a_flood(): void
    {
        $responses = [];

        for ($i = 0; $i < 22; $i++) {
            $responses[] = $this->actingAs($this->member, 'member')
                ->postJson('/chat/messages', ['body' => "line {$i}"])
                ->status();
        }

        $this->assertContains(429, $responses);
    }

    public function test_control_and_bidi_characters_are_stripped(): void
    {
        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => "safe\u{202E}reversed\x07"])
            ->assertCreated();

        $this->assertSame('safereversed', ChatMessage::query()->value('body'));
    }

    public function test_c1_control_characters_are_stripped(): void
    {
        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => "ab\u{0085}c\u{009F}d"])
            ->assertCreated();

        $this->assertSame('abcd', ChatMessage::query()->value('body'));
    }

    public function test_the_rate_limit_is_per_member_not_shared(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->actingAs($this->member, 'member')->postJson('/chat/messages', ['body' => "a{$i}"]);
        }

        $other = Member::create([
            'name' => 'Aki',
            'email' => 'aki2@example.test',
            'password' => 'password123!',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($other, 'member')
            ->postJson('/chat/messages', ['body' => 'fresh'])
            ->assertCreated();
    }

    public function test_an_external_link_is_rejected(): void
    {
        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'visit https://spam.example.test now'])
            ->assertStatus(422);
    }

    public function test_a_dead_broadcaster_does_not_fail_the_send(): void
    {
        // The failure has to happen BELOW the rescue point. ShouldRescue is
        // honoured in BroadcastManager::queue(), so a fake bound at the
        // Broadcasting\Factory contract replaces the very code being tested
        // (and would fatal anyway — the dispatcher calls queue(), which that
        // contract does not declare). Register a driver instead.
        Broadcast::extend('boom', fn (): Broadcaster => new class extends Broadcaster
        {
            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new RuntimeException('reverb is down');
            }
        });

        config([
            'broadcasting.default' => 'boom',
            'broadcasting.connections.boom' => ['driver' => 'boom'],
        ]);

        $this->actingAs($this->member, 'member')
            ->postJson('/chat/messages', ['body' => 'still saved'])
            ->assertCreated();

        $this->assertDatabaseHas('chat_messages', ['body' => 'still saved']);
    }

    public function test_a_member_may_delete_their_own_message_and_the_cache_is_dropped(): void
    {
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'oops']);

        $this->actingAs($this->member, 'member')
            ->deleteJson("/chat/messages/{$message->id}")
            ->assertOk();

        $this->assertSoftDeleted('chat_messages', ['id' => $message->id]);
    }

    public function test_deleting_drops_the_cached_history_and_logs_the_action(): void
    {
        $history = app(ChatHistory::class);
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'oops']);
        $history->replace(\App\Models\ChatRoom::lobby()->id, [['id' => $message->id, 'name' => 'Rin', 'body' => 'oops']]);
        $this->assertNotNull($history->recent(\App\Models\ChatRoom::lobby()->id, 10));

        $this->actingAs($this->member, 'member')
            ->deleteJson("/chat/messages/{$message->id}")
            ->assertOk();

        $this->assertNull($history->recent(\App\Models\ChatRoom::lobby()->id, 10));
        $this->assertDatabaseHas('chat_moderation_log', [
            'action' => 'delete_message',
            'actor_member_id' => $this->member->id,
            'target_member_id' => $this->member->id,
        ]);
        $this->assertSame(1, ChatModerationLog::query()->count());
    }

    public function test_a_moderator_deleting_someone_elses_message_is_logged(): void
    {
        $other = Member::create([
            'name' => 'Aki',
            'email' => 'aki3@example.test',
            'password' => 'password123!',
            'email_verified_at' => now(),
        ]);
        ChatMember::create(['member_id' => $this->member->id, 'rank' => 'mod', 'balance' => 0]);
        $message = ChatMessage::create(['member_id' => $other->id, 'body' => 'rude']);

        $this->actingAs($this->member, 'member')
            ->deleteJson("/chat/messages/{$message->id}")
            ->assertOk();

        $this->assertDatabaseHas('chat_moderation_log', [
            'action' => 'delete_message',
            'actor_member_id' => $this->member->id,
            'target_member_id' => $other->id,
        ]);
    }

    public function test_a_member_may_not_delete_someone_elses_message(): void
    {
        $other = Member::create([
            'name' => 'Aki',
            'email' => 'aki@example.test',
            'password' => 'password123!',
            'email_verified_at' => now(),
        ]);
        $message = ChatMessage::create(['member_id' => $other->id, 'body' => 'theirs']);

        $this->actingAs($this->member, 'member')
            ->deleteJson("/chat/messages/{$message->id}")
            ->assertForbidden();
    }

    public function test_author_and_mod_delete_close_open_reports(): void
    {
        $reporter = Member::create(['name' => 'Kai', 'email' => 'kai@example.test', 'password' => 'password123!', 'email_verified_at' => now()]);
        $mod = Member::create(['name' => 'Mod', 'email' => 'mod@example.test', 'password' => 'password123!', 'email_verified_at' => now()]);
        ChatMember::create(['member_id' => $mod->id, 'rank' => 'mod', 'balance' => 0]);

        foreach (['author' => $this->member, 'mod' => $mod] as $who => $actor) {
            $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => "oops $who"]);
            $report = \App\Models\Report::create([
                'member_id' => $reporter->id, 'target_type' => 'chat_message', 'target_id' => (string) $message->id,
                'reason' => 'spam', 'status' => 'open',
            ]);

            $this->actingAs($actor, 'member')->deleteJson("/chat/messages/{$message->id}")->assertOk();

            $this->assertSame('actioned', $report->fresh()->status, $who);
        }
    }
}
