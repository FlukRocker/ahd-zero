<?php

namespace Tests\Unit\Chat;

use App\Enums\ChatRank;
use App\Policies\ChatPolicy;
use Tests\TestCase;

class ChatPolicyTest extends TestCase
{
    private ChatPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ChatPolicy;
    }

    public function test_equal_rank_may_not_act(): void
    {
        $this->assertFalse($this->policy->canActOn(ChatRank::Mod, ChatRank::Mod));
        $this->assertFalse($this->policy->canActOn(ChatRank::MainOwner, ChatRank::MainOwner));
    }

    public function test_higher_rank_acts_on_lower(): void
    {
        $this->assertTrue($this->policy->canActOn(ChatRank::Mod, ChatRank::Member));
        $this->assertTrue($this->policy->canActOn(ChatRank::Owner, ChatRank::Mod));
        $this->assertFalse($this->policy->canActOn(ChatRank::Member, ChatRank::Mod));
    }

    public function test_only_owners_and_above_may_ban(): void
    {
        $this->assertFalse($this->policy->canBan(ChatRank::Mod));
        $this->assertTrue($this->policy->canBan(ChatRank::Owner));
        $this->assertTrue($this->policy->canBan(ChatRank::MainOwner));
    }

    public function test_mods_may_gag_and_kick_but_members_may_not(): void
    {
        $this->assertTrue($this->policy->canGag(ChatRank::Mod));
        $this->assertTrue($this->policy->canKick(ChatRank::Mod));
        $this->assertFalse($this->policy->canGag(ChatRank::Member));
        $this->assertFalse($this->policy->canKick(ChatRank::Member));
    }

    public function test_an_owner_may_make_mods_but_only_a_main_owner_may_make_owners(): void
    {
        $this->assertTrue($this->policy->canSetRank(ChatRank::Owner, ChatRank::Mod));
        $this->assertFalse($this->policy->canSetRank(ChatRank::Owner, ChatRank::Owner));
        $this->assertTrue($this->policy->canSetRank(ChatRank::MainOwner, ChatRank::Owner));
        $this->assertFalse($this->policy->canSetRank(ChatRank::MainOwner, ChatRank::MainOwner));
    }

    public function test_anyone_may_delete_their_own_message_and_a_mod_may_not_delete_a_mods(): void
    {
        $this->assertTrue($this->policy->canDeleteMessage(ChatRank::Member, ChatRank::Member, true));
        $this->assertTrue($this->policy->canDeleteMessage(ChatRank::Mod, ChatRank::Member, false));
        $this->assertFalse($this->policy->canDeleteMessage(ChatRank::Mod, ChatRank::Mod, false));
        $this->assertFalse($this->policy->canDeleteMessage(ChatRank::Member, ChatRank::Member, false));
    }
}
