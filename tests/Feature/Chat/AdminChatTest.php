<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatRank;
use App\Enums\CosmeticType;
use App\Events\Chat\MessageDeleted;
use App\Models\ChatCosmetic;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatRoom;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminChatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // User::$fillable is name/email/password only, so mass-assigning
        // email_verified_at silently drops it — and the dashboard group's
        // `verified` middleware would then bounce this admin. The factory
        // is what DashboardTest uses.
        $this->admin = User::factory()->create();

        $this->member = Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
            'email_verified_at' => now(),
        ]);
    }

    public function test_a_guest_cannot_reach_the_console(): void
    {
        $this->get('/dashboard/chat')->assertRedirect();
    }

    /**
     * Deliberately logs in through the real form rather than actingAs().
     *
     * actingAs($member, 'member') calls shouldUse('member'), which makes
     * the member guard the default — so the dashboard group's plain `auth`
     * would check THAT guard and let the member in, failing this test on a
     * codebase that is actually safe. Nothing in production ever sets the
     * default guard to member. If this test goes red, fix the test; do not
     * touch the shared admin middleware group.
     */
    public function test_a_member_cannot_reach_the_console(): void
    {
        $this->post('/member/login', [
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);

        // Without this the test is worthless: a login that fails for any
        // reason (throttle, Turnstile, a verification redirect) leaves a
        // guest request, which redirects too and passes for the wrong
        // reason.
        $this->assertAuthenticatedAs($this->member, 'member');

        $this->get('/dashboard/chat')->assertRedirect();
    }

    public function test_an_admin_sees_the_console(): void
    {
        $this->actingAs($this->admin)->get('/dashboard/chat')->assertOk();
    }

    public function test_index_lists_recent_messages_without_deleted_ones(): void
    {
        $kept = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'kept']);
        $gone = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'gone']);
        $gone->delete();

        $this->actingAs($this->admin)->get('/dashboard/chat')->assertInertia(
            fn ($page) => $page->has('messages', 1)
                ->where('messages.0.id', $kept->id)
                ->where('messages.0.body', 'kept')
                ->where('messages.0.member.id', $this->member->id)
                ->where('messages.0.member.name', 'Rin')
                ->has('messages.0.created_at')
        );
    }

    public function test_a_message_with_a_missing_member_row_does_not_break_the_console(): void
    {
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'orphan']);
        DB::table('members')->where('id', $this->member->id)->delete();

        $this->actingAs($this->admin)->get('/dashboard/chat')->assertOk()->assertInertia(
            fn ($page) => $page->has('messages', 1)
                ->where('messages.0.id', $message->id)
                ->where('messages.0.member', null)
        );
    }

    public function test_an_admin_grants_coins_and_the_ledger_matches(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/dashboard/chat/members/{$this->member->id}/grant", [
                'amount' => 250,
                'note' => 'launch gift',
            ])
            ->assertOk();

        $this->assertSame(250, ChatMember::query()->find($this->member->id)->balance);
        $this->assertDatabaseHas('chat_transactions', [
            'member_id' => $this->member->id,
            'delta' => 250,
            'reason' => 'admin_grant',
            'admin_id' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('chat_moderation_log', [
            'admin_id' => $this->admin->id,
            'target_member_id' => $this->member->id,
            'action' => 'grant_coins',
        ]);
    }

    public function test_a_negative_grant_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/dashboard/chat/members/{$this->member->id}/grant", ['amount' => -50])
            ->assertStatus(422);

        $this->assertDatabaseCount('chat_transactions', 0);
    }

    public function test_an_admin_sets_a_rank_without_holding_one(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/dashboard/chat/members/{$this->member->id}/rank", ['rank' => 'owner'])
            ->assertOk();

        $this->assertSame(ChatRank::Owner, ChatMember::query()->find($this->member->id)->rank);
    }

    public function test_a_cosmetic_with_a_bad_payload_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/dashboard/chat/cosmetics', [
                'type' => 'name_color',
                'name' => 'Broken',
                'slug' => 'broken',
                'payload' => ['color' => 'red; background: url(x)'],
                'price' => 10,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('chat_cosmetics', 0);
    }

    public function test_a_non_string_cosmetic_type_is_a_422_not_a_500(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/dashboard/chat/cosmetics', [
                'type' => ['name_color'],
                'name' => 'Broken',
                'slug' => 'broken',
                'payload' => ['color' => '#ff0000'],
                'price' => 10,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('chat_cosmetics', 0);
    }

    public function test_a_huge_sort_value_is_a_422_on_create_and_update(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/dashboard/chat/cosmetics', [
                'type' => 'name_color',
                'name' => 'Red',
                'slug' => 'red',
                'payload' => ['color' => '#ff0000'],
                'price' => 10,
                'sort' => 40000,
            ])
            ->assertStatus(422);

        $cosmetic = ChatCosmetic::create([
            'type' => CosmeticType::NameColor,
            'name' => 'Red',
            'slug' => 'red',
            'payload' => ['color' => '#ff0000'],
            'price' => 10,
            'active' => true,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/dashboard/chat/cosmetics/{$cosmetic->id}", ['sort' => 40000])
            ->assertStatus(422);
    }

    public function test_deleting_a_cosmetic_only_deactivates_it(): void
    {
        $cosmetic = ChatCosmetic::create([
            'type' => CosmeticType::NameColor,
            'name' => 'Sunset',
            'slug' => 'sunset',
            'payload' => ['color' => '#ff8800'],
            'price' => 100,
            'active' => true,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/dashboard/chat/cosmetics/{$cosmetic->id}")
            ->assertOk();

        // The row survives, so every inventory and ledger row still means
        // something.
        $this->assertDatabaseHas('chat_cosmetics', ['id' => $cosmetic->id, 'active' => false]);
    }

    public function test_a_guest_cannot_post_to_the_console(): void
    {
        $this->postJson("/dashboard/chat/members/{$this->member->id}/grant", ['amount' => 5])->assertUnauthorized();
        $this->assertDatabaseCount('chat_transactions', 0);
    }

    public function test_a_member_session_cannot_post_to_the_console(): void
    {
        $this->post('/member/login', ['email' => 'rin@example.test', 'password' => 'password123!']);
        $this->assertAuthenticatedAs($this->member, 'member');

        $this->postJson("/dashboard/chat/members/{$this->member->id}/grant", ['amount' => 5])->assertUnauthorized();
        $this->assertDatabaseCount('chat_transactions', 0);
        $this->assertSame(0, ChatMember::query()->count());
    }

    public function test_an_unknown_member_is_a_404_and_creates_nothing(): void
    {
        $this->actingAs($this->admin)->postJson('/dashboard/chat/members/99999/grant', ['amount' => 5])->assertNotFound();
        $this->actingAs($this->admin)->postJson('/dashboard/chat/members/99999/rank', ['rank' => 'mod'])->assertNotFound();
        $this->actingAs($this->admin)->postJson('/dashboard/chat/members/99999/ban')->assertNotFound();
        $this->assertSame(0, ChatMember::query()->count());
    }

    public function test_a_non_numeric_member_id_is_a_404(): void
    {
        $this->actingAs($this->admin)->postJson('/dashboard/chat/members/abc/grant', ['amount' => 5])->assertNotFound();
    }

    public function test_the_grant_path_does_not_wrap_the_wallet_insert_in_a_transaction(): void
    {
        $baseline = DB::transactionLevel();
        $levels = [];

        DB::listen(function ($query) use (&$levels): void {
            if (str_contains($query->sql, 'insert or ignore into "chat_members"') || str_contains($query->sql, 'insert ignore into `chat_members`')) {
                $levels[] = DB::transactionLevel();
            }
        });

        $this->actingAs($this->admin)
            ->postJson("/dashboard/chat/members/{$this->member->id}/grant", ['amount' => 5])
            ->assertOk();

        $this->assertSame([$baseline], $levels);
        $this->assertSame($baseline, DB::transactionLevel());
    }

    public function test_an_admin_bans_and_unbans_with_one_log_row_each(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/dashboard/chat/members/{$this->member->id}/ban", ['minutes' => 60, 'reason' => 'spam'])
            ->assertOk();

        $this->assertTrue(ChatMember::query()->find($this->member->id)->isBanned());
        $this->assertDatabaseHas('chat_moderation_log', [
            'admin_id' => $this->admin->id,
            'actor_member_id' => null,
            'target_member_id' => $this->member->id,
            'action' => 'ban',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/dashboard/chat/members/{$this->member->id}/ban", ['unban' => true])
            ->assertOk();

        $this->assertFalse(ChatMember::query()->find($this->member->id)->isBanned());
        $this->assertSame(1, ChatModerationLog::query()->where('action', 'unban')->where('admin_id', $this->admin->id)->count());
        $this->assertSame(2, ChatModerationLog::query()->count());
    }

    public function test_rank_change_is_logged_with_the_admin_id_and_no_actor(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/dashboard/chat/members/{$this->member->id}/rank", ['rank' => 'main_owner'])
            ->assertOk();

        $this->assertSame(ChatRank::MainOwner, ChatMember::query()->find($this->member->id)->rank);
        $this->assertDatabaseHas('chat_moderation_log', [
            'admin_id' => $this->admin->id,
            'actor_member_id' => null,
            'action' => 'rank_change',
        ]);
        $this->assertSame(1, ChatModerationLog::query()->count());
    }

    public function test_an_admin_grants_a_cosmetic(): void
    {
        $cosmetic = ChatCosmetic::create([
            'type' => CosmeticType::NameColor,
            'name' => 'Sunset',
            'slug' => 'sunset',
            'payload' => ['color' => '#ff8800'],
            'price' => 100,
            'active' => true,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/dashboard/chat/members/{$this->member->id}/cosmetics", ['cosmetic_id' => $cosmetic->id])
            ->assertOk();

        $this->assertDatabaseHas('chat_cosmetic_member', ['member_id' => $this->member->id, 'cosmetic_id' => $cosmetic->id]);
        $this->assertDatabaseHas('chat_moderation_log', [
            'admin_id' => $this->admin->id,
            'action' => 'grant_cosmetic',
        ]);
    }

    public function test_a_valid_cosmetic_is_created(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/dashboard/chat/cosmetics', [
                'type' => 'name_color',
                'name' => 'Sunset',
                'slug' => 'sunset',
                'payload' => ['color' => '#ff8800'],
                'price' => 10,
            ])
            ->assertOk();

        $this->assertDatabaseHas('chat_cosmetics', ['slug' => 'sunset', 'active' => true]);
    }

    public function test_create_cosmetic_422_returns_field_errors_for_a_bad_colour_and_duplicate_slug(): void
    {
        ChatCosmetic::create([
            'type' => CosmeticType::NameColor,
            'name' => 'Existing',
            'slug' => 'taken',
            'payload' => ['color' => '#ff0000'],
            'price' => 5,
            'active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/dashboard/chat/cosmetics', [
                'type' => 'name_color',
                'name' => 'Dupe',
                'slug' => 'taken',
                'payload' => ['color' => 'red;background:url(//evil)'],
                'price' => 10,
                'sort' => 0,
            ])
            ->assertStatus(422);

        $errors = array_keys($response->json('errors'));
        $this->assertContains('slug', $errors);
        $this->assertNotEmpty(array_filter($errors, fn ($k) => str_starts_with($k, 'payload')));
        $this->assertDatabaseCount('chat_cosmetics', 1);
        $this->assertDatabaseMissing('chat_cosmetics', ['name' => 'Dupe']);
    }

    public function test_updating_a_cosmetic_with_a_bad_payload_is_rejected(): void
    {
        $cosmetic = ChatCosmetic::create([
            'type' => CosmeticType::NameColor,
            'name' => 'Sunset',
            'slug' => 'sunset',
            'payload' => ['color' => '#ff8800'],
            'price' => 100,
            'active' => true,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/dashboard/chat/cosmetics/{$cosmetic->id}", ['payload' => ['color' => 'red; background: url(x)']])
            ->assertStatus(422);

        $this->assertSame(['color' => '#ff8800'], $cosmetic->fresh()->payload);
    }

    public function test_an_admin_deleting_a_message_writes_an_admin_log_row(): void
    {
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'rude']);

        $this->actingAs($this->admin)
            ->deleteJson("/dashboard/chat/messages/{$message->id}")
            ->assertOk();

        $this->assertSoftDeleted('chat_messages', ['id' => $message->id]);
        $this->assertDatabaseHas('chat_messages', ['id' => $message->id, 'deleted_by_admin_id' => $this->admin->id]);
        $this->assertDatabaseHas('chat_moderation_log', [
            'action' => 'delete_message',
            'admin_id' => $this->admin->id,
            'actor_member_id' => null,
            'target_member_id' => $this->member->id,
        ]);
        $this->assertSame(1, ChatModerationLog::query()->count());
    }

    public function test_admin_creates_room(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/dashboard/chat/rooms', ['name' => 'Anime talk', 'slug' => 'anime', 'topic' => 'Seasonal', 'sort' => 5])
            ->assertOk();

        $this->assertDatabaseHas('chat_rooms', ['slug' => 'anime', 'name' => 'Anime talk', 'topic' => 'Seasonal', 'sort' => 5, 'active' => true]);

        $this->actingAs($this->admin)->get('/dashboard/chat')->assertInertia(
            fn ($page) => $page->has('rooms', 2)
                ->has('rooms.0', fn ($room) => $room->hasAll(['id', 'slug', 'name', 'topic', 'sort', 'active']))
        );
    }

    public function test_duplicate_or_bad_slug_is_rejected(): void
    {
        $base = ['name' => 'X', 'topic' => null, 'sort' => 0];

        foreach (['lobby', 'Bad Slug', 'UPPER', 'a', 'has_underscore', str_repeat('a', 41)] as $slug) {
            $this->actingAs($this->admin)
                ->postJson('/dashboard/chat/rooms', $base + ['slug' => $slug])
                ->assertStatus(422)
                ->assertJsonValidationErrors('slug');
        }

        $this->assertSame(1, ChatRoom::query()->count());
    }

    public function test_update_never_changes_slug(): void
    {
        $room = ChatRoom::create(['slug' => 'anime', 'name' => 'Anime', 'sort' => 1, 'active' => true]);

        $this->actingAs($this->admin)
            ->patchJson("/dashboard/chat/rooms/{$room->id}", ['name' => 'Renamed', 'topic' => 'New', 'sort' => 7, 'active' => false, 'slug' => 'hijack'])
            ->assertOk();

        $fresh = $room->fresh();
        $this->assertSame('anime', $fresh->slug);
        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame('New', $fresh->topic);
        $this->assertSame(7, $fresh->sort);
        $this->assertFalse($fresh->active);
    }

    public function test_lobby_cannot_be_deactivated(): void
    {
        $lobby = ChatRoom::lobby();

        $this->actingAs($this->admin)
            ->patchJson("/dashboard/chat/rooms/{$lobby->id}", ['active' => false])
            ->assertStatus(422);

        $this->assertTrue($lobby->fresh()->active);

        $this->actingAs($this->admin)
            ->patchJson("/dashboard/chat/rooms/{$lobby->id}", ['name' => 'Main hall'])
            ->assertOk();
        $this->assertSame('Main hall', $lobby->fresh()->name);
    }

    public function test_non_admin_cannot_manage_rooms(): void
    {
        $this->postJson('/dashboard/chat/rooms', ['name' => 'X', 'slug' => 'xx'])->assertUnauthorized();
        $this->patchJson('/dashboard/chat/rooms/1', ['name' => 'X'])->assertUnauthorized();

        $this->post('/member/login', ['email' => 'rin@example.test', 'password' => 'password123!']);
        $this->assertAuthenticatedAs($this->member, 'member');

        $this->postJson('/dashboard/chat/rooms', ['name' => 'X', 'slug' => 'xx'])->assertUnauthorized();
        $this->patchJson('/dashboard/chat/rooms/1', ['name' => 'X'])->assertUnauthorized();

        $this->assertSame(1, ChatRoom::query()->count());
        $this->assertSame('Lobby', ChatRoom::lobby()->name);
    }

    public function test_admin_delete_message_uses_its_room(): void
    {
        Event::fake([MessageDeleted::class]);
        $room = ChatRoom::create(['slug' => 'anime', 'name' => 'Anime', 'sort' => 1, 'active' => true]);
        $message = ChatMessage::create(['member_id' => $this->member->id, 'room_id' => $room->id, 'body' => 'rude']);

        $this->actingAs($this->admin)->deleteJson("/dashboard/chat/messages/{$message->id}")->assertOk();

        Event::assertDispatched(
            MessageDeleted::class,
            fn (MessageDeleted $e): bool => $e->broadcastOn()->name === 'chat.room.'.$room->id && $e->messageId === $message->id,
        );
        Event::assertNotDispatched(MessageDeleted::class, fn (MessageDeleted $e): bool => $e->broadcastOn()->name === 'chat.room.1');
    }
}
