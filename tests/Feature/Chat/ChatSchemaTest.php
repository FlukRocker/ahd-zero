<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatRank;
use App\Models\ChatMember;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_chat_member_row_defaults_to_the_member_rank_and_no_coins(): void
    {
        $member = Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);

        $chatMember = ChatMember::create(['member_id' => $member->id]);

        $this->assertSame(ChatRank::Member, $chatMember->fresh()->rank);
        $this->assertSame(0, $chatMember->fresh()->balance);
    }

    public function test_the_rank_ladder_only_outranks_downwards(): void
    {
        $this->assertTrue(ChatRank::Owner->outranks(ChatRank::Mod));
        $this->assertFalse(ChatRank::Mod->outranks(ChatRank::Mod));
        $this->assertFalse(ChatRank::Mod->outranks(ChatRank::Owner));
    }
}
