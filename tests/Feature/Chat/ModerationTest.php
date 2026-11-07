<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatRank;
use App\Events\Chat\MemberSanctioned;
use App\Models\ChatMember;
use App\Models\ChatModerationLog;
use App\Models\Member;
use App\Services\Chat\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, ChatRank $rank = ChatRank::Member): Member
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

    public function test_a_mod_can_gag_a_member(): void
    {
        $mod = $this->member('Mod', ChatRank::Mod);
        $target = $this->member('Rin');

        $this->actingAs($mod, 'member')->postJson('/chat/moderate', [
            'action' => 'gag',
            'target_uuid' => $target->uuid,
            'minutes' => 10,
        ])->assertOk();

        $this->assertTrue(ChatMember::query()->find($target->id)->isGagged());
        $this->assertDatabaseHas('chat_moderation_log', [
            'actor_member_id' => $mod->id,
            'target_member_id' => $target->id,
            'action' => 'gag',
        ]);
    }

    public function test_a_mod_cannot_gag_another_mod(): void
    {
        $mod = $this->member('Mod', ChatRank::Mod);
        $other = $this->member('Other', ChatRank::Mod);

        $this->actingAs($mod, 'member')->postJson('/chat/moderate', [
            'action' => 'gag',
            'target_uuid' => $other->uuid,
            'minutes' => 10,
        ])->assertForbidden();

        $this->assertFalse(ChatMember::query()->find($other->id)->isGagged());
    }

    public function test_a_mod_cannot_ban_but_an_owner_can(): void
    {
        $mod = $this->member('Mod', ChatRank::Mod);
        $owner = $this->member('Owner', ChatRank::Owner);
        $target = $this->member('Rin');

        $this->actingAs($mod, 'member')->postJson('/chat/moderate', [
            'action' => 'ban', 'target_uuid' => $target->uuid,
        ])->assertForbidden();

        $this->actingAs($owner, 'member')->postJson('/chat/moderate', [
            'action' => 'ban', 'target_uuid' => $target->uuid, 'reason' => 'spam',
        ])->assertOk();

        $this->assertTrue(ChatMember::query()->find($target->id)->isBanned());
    }

    public function test_unban_clears_every_ban_column(): void
    {
        $owner = $this->member('Owner', ChatRank::Owner);
        $target = $this->member('Rin');
        ChatMember::query()->where('member_id', $target->id)->update([
            'banned_at' => now(), 'banned_until' => now()->addDay(), 'ban_reason' => 'spam',
        ]);

        $this->actingAs($owner, 'member')->postJson('/chat/moderate', [
            'action' => 'unban', 'target_uuid' => $target->uuid,
        ])->assertOk();

        $row = ChatMember::query()->find($target->id);
        $this->assertNull($row->banned_at);
        $this->assertNull($row->banned_until);
        $this->assertNull($row->ban_reason);
    }

    public function test_an_owner_may_make_a_mod_but_not_another_owner(): void
    {
        $owner = $this->member('Owner', ChatRank::Owner);
        $target = $this->member('Rin');

        $this->actingAs($owner, 'member')->postJson('/chat/moderate', [
            'action' => 'rank', 'target_uuid' => $target->uuid, 'rank' => 'mod',
        ])->assertOk();

        $this->assertSame(ChatRank::Mod, ChatMember::query()->find($target->id)->rank);

        $this->actingAs($owner, 'member')->postJson('/chat/moderate', [
            'action' => 'rank', 'target_uuid' => $target->uuid, 'rank' => 'owner',
        ])->assertForbidden();
    }

    public function test_a_plain_member_cannot_moderate_at_all(): void
    {
        $member = $this->member('Rin');
        $target = $this->member('Aki');

        $this->actingAs($member, 'member')->postJson('/chat/moderate', [
            'action' => 'kick', 'target_uuid' => $target->uuid,
        ])->assertForbidden();
    }

    public function test_a_target_who_never_spoke_can_be_gagged(): void
    {
        $mod = $this->member('Mod', ChatRank::Mod);
        $target = Member::create([
            'name' => 'Ghost', 'email' => 'ghost@example.test',
            'password' => 'password123!', 'email_verified_at' => now(),
        ]);
        $this->assertDatabaseMissing('chat_members', ['member_id' => $target->id]);

        $this->actingAs($mod, 'member')->postJson('/chat/moderate', [
            'action' => 'gag', 'target_uuid' => $target->uuid, 'minutes' => 5,
        ])->assertOk();

        $this->assertTrue(ChatMember::query()->find($target->id)->isGagged());
    }

    public function test_every_action_writes_a_log_row_and_broadcasts(): void
    {
        Event::fake([MemberSanctioned::class]);
        $owner = $this->member('Owner', ChatRank::Owner);
        $target = $this->member('Rin');

        foreach (['gag', 'ungag', 'kick', 'ban', 'unban'] as $action) {
            $this->actingAs($owner, 'member')->postJson('/chat/moderate', [
                'action' => $action, 'target_uuid' => $target->uuid,
            ])->assertOk();
        }
        $this->actingAs($owner, 'member')->postJson('/chat/moderate', [
            'action' => 'rank', 'target_uuid' => $target->uuid, 'rank' => 'mod',
        ])->assertOk();

        $this->assertDatabaseCount('chat_moderation_log', 6);
        $this->assertDatabaseHas('chat_moderation_log', ['action' => 'rank_change', 'target_member_id' => $target->id]);
        Event::assertDispatchedTimes(MemberSanctioned::class, 6);
        Event::assertDispatched(MemberSanctioned::class, fn ($e) => $e->uuid === $target->uuid && $e->action === 'kick');
    }

    public function test_a_failed_log_insert_rolls_the_sanction_back(): void
    {
        Event::fake([MemberSanctioned::class]);
        $target = $this->member('Rin');

        ChatModerationLog::creating(fn () => throw new \RuntimeException('log insert failed'));

        try {
            $this->app->make(ModerationService::class)->ban(ChatMember::query()->findOrFail($target->id), null, 'spam', null, null);
            $this->fail('The log failure should have propagated.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNull(ChatMember::query()->findOrFail($target->id)->banned_at);
        Event::assertNotDispatched(MemberSanctioned::class);
    }

    public function test_a_refused_action_writes_no_log_row(): void
    {
        $mod = $this->member('Mod', ChatRank::Mod);
        $other = $this->member('Other', ChatRank::Mod);

        $this->actingAs($mod, 'member')->postJson('/chat/moderate', [
            'action' => 'gag', 'target_uuid' => $other->uuid,
        ])->assertForbidden();

        $this->assertDatabaseCount('chat_moderation_log', 0);
    }

    public function test_an_owner_may_not_demote_another_owner(): void
    {
        $owner = $this->member('Owner', ChatRank::Owner);
        $other = $this->member('Other', ChatRank::Owner);

        $this->actingAs($owner, 'member')->postJson('/chat/moderate', [
            'action' => 'rank', 'target_uuid' => $other->uuid, 'rank' => 'member',
        ])->assertForbidden();

        $this->assertSame(ChatRank::Owner, ChatMember::query()->find($other->id)->rank);
    }

    public function test_a_mod_may_not_set_ranks_at_all(): void
    {
        $mod = $this->member('Mod', ChatRank::Mod);
        $target = $this->member('Rin');

        $this->actingAs($mod, 'member')->postJson('/chat/moderate', [
            'action' => 'rank', 'target_uuid' => $target->uuid, 'rank' => 'member',
        ])->assertForbidden();
    }

    public function test_nobody_mints_their_own_rank(): void
    {
        $owner = $this->member('Owner', ChatRank::Owner);

        $this->actingAs($owner, 'member')->postJson('/chat/moderate', [
            'action' => 'rank', 'target_uuid' => $owner->uuid, 'rank' => 'main_owner',
        ])->assertForbidden();

        $this->assertSame(ChatRank::Owner, ChatMember::query()->find($owner->id)->rank);
    }

    public function test_a_main_owner_may_promote_and_demote_owners(): void
    {
        $main = $this->member('Main', ChatRank::MainOwner);
        $target = $this->member('Rin');

        $this->actingAs($main, 'member')->postJson('/chat/moderate', [
            'action' => 'rank', 'target_uuid' => $target->uuid, 'rank' => 'owner',
        ])->assertOk();
        $this->assertSame(ChatRank::Owner, ChatMember::query()->find($target->id)->rank);

        $this->actingAs($main, 'member')->postJson('/chat/moderate', [
            'action' => 'rank', 'target_uuid' => $target->uuid, 'rank' => 'member',
        ])->assertOk();
        $this->assertSame(ChatRank::Member, ChatMember::query()->find($target->id)->rank);
    }
}
