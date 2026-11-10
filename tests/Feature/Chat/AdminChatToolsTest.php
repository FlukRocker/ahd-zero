<?php

namespace Tests\Feature\Chat;

use App\Enums\CosmeticType;
use App\Events\Chat\MessagePinned;
use App\Events\Chat\MessageSent;
use App\Events\Chat\MessagesPurged;
use App\Models\ChatCosmetic;
use App\Models\ChatCosmeticMember;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatRoom;
use App\Models\ChatTransaction;
use App\Models\Member;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Support\Chat\ChatPins;
use App\Support\Chat\ChatSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BacksUpSiteSettings;
use Tests\TestCase;

class AdminChatToolsTest extends TestCase
{
    use BacksUpSiteSettings;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([MessagesPurged::class, MessagePinned::class, MessageSent::class]);
        $this->backUpSiteSettings();
        ChatPins::clear(ChatRoom::lobby()->id);
        Cache::forget('chat:admin:stats');
        $this->admin = User::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->restoreSiteSettings();
        parent::tearDown();
    }

    private function member(string $name): Member
    {
        return Member::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'password123!']);
    }

    private function cosmetic(): ChatCosmetic
    {
        return ChatCosmetic::create(['type' => CosmeticType::NameColor, 'name' => 'Red', 'slug' => 'red', 'payload' => ['color' => '#ff0000'], 'price' => 1, 'active' => true]);
    }

    public function test_tools_are_admin_only(): void
    {
        $this->getJson('/dashboard/chat/tools/stats')->assertUnauthorized();
        $this->actingAs($this->member('Aki'), 'member')->getJson('/dashboard/chat/tools/stats')->assertForbidden();
    }

    public function test_owners_endpoint_lists_who_owns_a_cosmetic_and_is_admin_only(): void
    {
        $aki = $this->member('Aki');
        $red = $this->cosmetic();
        ChatCosmeticMember::create(['member_id' => $aki->id, 'cosmetic_id' => $red->id, 'acquired_at' => now(), 'source' => 'purchase', 'equipped' => true]);

        $this->getJson("/dashboard/chat/tools/cosmetics/{$red->id}/owners")->assertUnauthorized();
        $this->actingAs($aki, 'member')->getJson("/dashboard/chat/tools/cosmetics/{$red->id}/owners")->assertForbidden();

        $this->actingAs($this->admin)->getJson("/dashboard/chat/tools/cosmetics/{$red->id}/owners")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('owners.0.name', 'Aki')
            ->assertJsonPath('owners.0.source', 'purchase')
            ->assertJsonPath('owners.0.equipped', true);

        $this->actingAs($this->admin)->get('/dashboard/chat')->assertInertia(
            fn ($page) => $page->where('cosmetics.0.owners_count', 1)
        );
    }

    public function test_admin_can_equip_and_unequip_an_owned_cosmetic(): void
    {
        $aki = $this->member('Aki');
        $red = $this->cosmetic();
        $other = $this->member('Bo');
        ChatCosmeticMember::create(['member_id' => $aki->id, 'cosmetic_id' => $red->id, 'acquired_at' => now(), 'source' => 'grant', 'equipped' => false]);
        $url = "/dashboard/chat/tools/members/{$aki->id}/equip-cosmetic";

        $this->actingAs($aki, 'member')->postJson($url, ['cosmetic_id' => $red->id, 'equipped' => true])->assertForbidden();

        $this->actingAs($this->admin)->postJson($url, ['cosmetic_id' => $red->id, 'equipped' => true])->assertOk();
        $this->assertTrue(ChatCosmeticMember::query()->where('member_id', $aki->id)->value('equipped'));

        $this->actingAs($this->admin)->postJson($url, ['cosmetic_id' => $red->id, 'equipped' => false])->assertOk();
        $this->assertFalse(ChatCosmeticMember::query()->where('member_id', $aki->id)->value('equipped'));

        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/members/{$other->id}/equip-cosmetic", ['cosmetic_id' => $red->id, 'equipped' => true])->assertStatus(422);
        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/members/{$other->id}/equip-cosmetic", ['cosmetic_id' => $red->id, 'equipped' => false])->assertStatus(422);
    }

    public function test_online_endpoint_lists_ids_for_admins_while_the_public_list_has_none(): void
    {
        $aki = $this->member('Aki');
        $this->actingAs($aki, 'member')->post('/chat/presence');

        $this->actingAs($this->admin)->getJson('/dashboard/chat/tools/online')->assertOk()->assertJsonPath('online.0.id', $aki->id);
        $this->assertArrayNotHasKey('id', app(\App\Services\Chat\PresenceService::class)->online()[0]);
    }

    public function test_stats_count_messages_members_and_coins(): void
    {
        $aki = $this->member('Aki');
        ChatMember::create(['member_id' => $aki->id, 'rank' => 'member', 'balance' => 42]);
        ChatMessage::create(['member_id' => $aki->id, 'body' => 'a', 'room_id' => ChatRoom::lobby()->id]);
        ChatMessage::create(['member_id' => $aki->id, 'body' => 'b', 'room_id' => ChatRoom::lobby()->id]);

        $json = $this->actingAs($this->admin)->getJson('/dashboard/chat/tools/stats')->assertOk()->json();

        $this->assertSame(42, $json['coinsInCirculation']);
        $this->assertSame(1, $json['activeMembers7d']);
        $this->assertSame(['name' => 'Aki', 'count' => 2], $json['topChatters'][0]);
        $this->assertCount(14, $json['perDay']);
        $this->assertSame(2, end($json['perDay'])['count']);
    }

    public function test_message_search_escapes_wildcards_and_needs_a_real_query(): void
    {
        $aki = $this->member('Aki');
        $room = ChatRoom::lobby()->id;
        ChatMessage::create(['member_id' => $aki->id, 'body' => '100% sure', 'room_id' => $room]);
        ChatMessage::create(['member_id' => $aki->id, 'body' => 'hello world', 'room_id' => $room]);

        $this->actingAs($this->admin)->getJson('/dashboard/chat/tools/messages?q=%25')->assertStatus(422);
        $this->actingAs($this->admin)->getJson('/dashboard/chat/tools/messages?q=0%25')->assertOk()->assertJsonCount(1, 'messages');
        $this->actingAs($this->admin)->getJson('/dashboard/chat/tools/messages?q=world&member=Aki')->assertOk()->assertJsonPath('messages.0.body', 'hello world');
    }

    public function test_taking_coins_debits_with_a_ledger_row_and_refuses_to_go_negative(): void
    {
        $aki = $this->member('Aki');
        ChatMember::create(['member_id' => $aki->id, 'rank' => 'member', 'balance' => 10]);

        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/members/{$aki->id}/take-coins", ['amount' => 4, 'note' => 'abuse'])->assertOk();
        $this->assertSame(6, ChatMember::find($aki->id)->balance);
        $tx = ChatTransaction::query()->latest('id')->first();
        $this->assertSame([-4, 6, 'admin_grant'], [$tx->delta, $tx->balance_after, $tx->reason]);

        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/members/{$aki->id}/take-coins", ['amount' => 7])->assertStatus(422);
        $this->assertSame(6, ChatMember::find($aki->id)->balance);
    }

    public function test_revoking_a_cosmetic_removes_it_clears_cached_styles_and_logs_with_an_existing_action(): void
    {
        $aki = $this->member('Aki');
        $red = $this->cosmetic();
        ChatCosmeticMember::create(['member_id' => $aki->id, 'cosmetic_id' => $red->id, 'acquired_at' => now(), 'source' => 'grant', 'equipped' => true]);
        $chat = app(ChatService::class);
        $chat->remember(ChatRoom::lobby()->id, ['id' => 1, 'stale' => true]);

        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/members/{$aki->id}/revoke-cosmetic", ['cosmetic_id' => $red->id])->assertOk();

        $this->assertDatabaseMissing('chat_cosmetic_member', ['member_id' => $aki->id]);
        $this->assertNotContains(true, array_column($chat->history(ChatRoom::lobby()->id), 'stale'));
        $log = ChatModerationLog::query()->latest('id')->first();
        // The prod column is an ENUM: only already-allowed values may be written.
        $this->assertSame('grant_cosmetic', $log->action);
        $this->assertSame('revoke_cosmetic', $log->meta['op']);
    }

    public function test_purging_a_member_deletes_all_their_messages_everywhere_and_announces_once_per_room(): void
    {
        $aki = $this->member('Aki');
        $bo = $this->member('Bo');
        $anime = ChatRoom::create(['slug' => 'anime', 'name' => 'Anime', 'active' => true, 'sort' => 1]);
        $lobby = ChatRoom::lobby()->id;
        ChatMessage::create(['member_id' => $aki->id, 'body' => 'a1', 'room_id' => $lobby]);
        $pinned = ChatMessage::create(['member_id' => $aki->id, 'body' => 'a2', 'room_id' => $anime->id]);
        ChatMessage::create(['member_id' => $bo->id, 'body' => 'b1', 'room_id' => $lobby]);
        ChatPins::set($anime->id, $pinned->id);

        \App\Models\Report::query()->create(['target_type' => 'chat_message', 'target_id' => (string) $pinned->id, 'member_id' => $bo->id, 'reason' => 'spam', 'status' => 'open']);

        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/members/{$aki->id}/purge")->assertOk()->assertJsonPath('deleted', 2);
        $this->assertSame('actioned', \App\Models\Report::query()->first()->status);

        $this->assertSame(1, ChatMessage::query()->count());
        $this->assertNull(ChatPins::get($anime->id));
        Event::assertDispatchedTimes(MessagesPurged::class, 2);
        Event::assertDispatched(MessagesPurged::class, fn ($e) => $e->uuid === $aki->uuid);
        $this->assertSame('delete_message', ChatModerationLog::query()->latest('id')->first()->action);
    }

    public function test_clearing_a_room_only_clears_that_room(): void
    {
        $aki = $this->member('Aki');
        $anime = ChatRoom::create(['slug' => 'anime', 'name' => 'Anime', 'active' => true, 'sort' => 1]);
        ChatMessage::create(['member_id' => $aki->id, 'body' => 'x', 'room_id' => $anime->id]);
        ChatMessage::create(['member_id' => $aki->id, 'body' => 'y', 'room_id' => ChatRoom::lobby()->id]);

        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/rooms/{$anime->id}/clear")->assertOk()->assertJsonPath('deleted', 1);

        $this->assertSame(['y'], ChatMessage::query()->pluck('body')->all());
        Event::assertDispatched(MessagesPurged::class, fn ($e) => $e->roomId === $anime->id && $e->uuid === null);
    }

    public function test_kick_gag_and_ungag_apply_through_the_moderation_service(): void
    {
        $aki = $this->member('Aki');

        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/members/{$aki->id}/gag", ['minutes' => 5])->assertOk();
        $this->assertTrue(ChatMember::find($aki->id)->isGagged());
        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/members/{$aki->id}/gag", ['ungag' => true])->assertOk();
        $this->assertFalse(ChatMember::find($aki->id)->isGagged());
        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/members/{$aki->id}/kick")->assertOk();
        $this->assertTrue(ChatMember::find($aki->id)->isKicked());
    }

    public function test_member_detail_returns_inventory_balance_and_history(): void
    {
        $aki = $this->member('Aki');
        $red = $this->cosmetic();
        ChatCosmeticMember::create(['member_id' => $aki->id, 'cosmetic_id' => $red->id, 'acquired_at' => now(), 'source' => 'grant', 'equipped' => false]);
        ChatMessage::create(['member_id' => $aki->id, 'body' => 'hey', 'room_id' => ChatRoom::lobby()->id]);

        $this->actingAs($this->admin)->getJson("/dashboard/chat/tools/members/{$aki->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Aki')
            ->assertJsonPath('cosmetics.0.name', 'Red')
            ->assertJsonPath('messages.0.body', 'hey');
    }

    public function test_settings_round_trip_announcement_words_and_room_controls(): void
    {
        $lobby = ChatRoom::lobby()->id;
        $this->actingAs($this->admin)->postJson('/dashboard/chat/tools/announcement', ['announcement' => 'Hi all'])->assertOk();
        $this->actingAs($this->admin)->postJson('/dashboard/chat/tools/words', ['words' => "Foo\nbar"])->assertOk()->assertJsonPath('count', 2);
        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/rooms/{$lobby}/controls", ['locked' => true, 'slow' => 15])->assertOk();
        $this->actingAs($this->admin)->postJson("/dashboard/chat/tools/rooms/{$lobby}/controls", ['locked' => true, 'slow' => 99999])->assertStatus(422);

        $json = $this->actingAs($this->admin)->getJson('/dashboard/chat/tools/settings')->assertOk()->json();

        $this->assertSame('Hi all', $json['announcement']);
        $this->assertSame("foo\nbar", $json['words']);
        $this->assertSame([true, 15], [$json['rooms'][0]['locked'], $json['rooms'][0]['slow']]);
        $this->assertSame(['locked' => true, 'slow' => 15], ChatSettings::room($lobby));
    }
}
