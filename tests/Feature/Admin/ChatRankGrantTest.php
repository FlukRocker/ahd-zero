<?php

namespace Tests\Feature\Admin;

use App\Enums\ChatRank;
use App\Models\ChatMember;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatRankGrantTest extends TestCase
{
    use RefreshDatabase;

    private function member(): Member
    {
        return Member::create(['name' => 'Rin', 'email' => 'rin@example.test', 'password' => 'password123!']);
    }

    public function test_an_admin_can_grant_chat_admin_to_a_member_who_never_opened_chat(): void
    {
        $member = $this->member();
        $this->assertNull(ChatMember::query()->where('member_id', $member->id)->first());

        $this->actingAs(User::factory()->create())
            ->postJson('/dashboard/chat/rank', ['email' => ' RIN@example.test ', 'rank' => 'owner'])
            ->assertOk();

        $this->assertSame(ChatRank::Owner, ChatMember::query()->where('member_id', $member->id)->firstOrFail()->rank);
        $this->assertDatabaseHas('chat_moderation_log', ['action' => 'rank_change', 'target_member_id' => $member->id]);
    }

    public function test_unknown_email_and_bad_rank_are_rejected(): void
    {
        $this->member();
        $admin = User::factory()->create();

        $this->actingAs($admin)->postJson('/dashboard/chat/rank', ['email' => 'nobody@example.test', 'rank' => 'owner'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->actingAs($admin)->postJson('/dashboard/chat/rank', ['email' => 'rin@example.test', 'rank' => 'god'])
            ->assertStatus(422)->assertJsonValidationErrors('rank');
    }

    public function test_a_member_or_guest_cannot_grant_ranks(): void
    {
        $member = $this->member();

        $this->postJson('/dashboard/chat/rank', ['email' => 'rin@example.test', 'rank' => 'owner'])->assertUnauthorized();
        $this->actingAs($member, 'member')->postJson('/dashboard/chat/rank', ['email' => 'rin@example.test', 'rank' => 'owner'])
            ->assertForbidden();
        $this->assertNull(ChatMember::query()->where('member_id', $member->id)->first());
    }
}
