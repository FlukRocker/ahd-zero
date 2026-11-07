<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMember;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMemberSanctionTest extends TestCase
{
    use RefreshDatabase;

    private function chatMember(array $attributes = []): ChatMember
    {
        $member = Member::create([
            'name' => 'Rin',
            'email' => 'rin'.uniqid().'@example.test',
            'password' => 'password123!',
        ]);

        return ChatMember::create(['member_id' => $member->id] + $attributes);
    }

    public function test_a_permanent_ban_is_active(): void
    {
        $this->assertTrue($this->chatMember(['banned_at' => now()])->isBanned());
    }

    public function test_a_timed_ban_is_active_until_it_expires(): void
    {
        $this->assertTrue($this->chatMember([
            'banned_at' => now(),
            'banned_until' => now()->addMinutes(5),
        ])->isBanned());
    }

    public function test_an_expired_ban_is_not_active_and_needs_no_sweep(): void
    {
        $this->assertFalse($this->chatMember([
            'banned_at' => now()->subDay(),
            'banned_until' => now()->subMinute(),
        ])->isBanned());
    }

    public function test_an_expired_gag_lets_the_member_speak_again(): void
    {
        $chatMember = $this->chatMember(['gagged_until' => now()->subSecond()]);

        $this->assertFalse($chatMember->isGagged());
        $this->assertTrue($chatMember->canSpeak());
    }

    public function test_a_live_kick_blocks_speaking(): void
    {
        $chatMember = $this->chatMember(['kicked_until' => now()->addSeconds(60)]);

        $this->assertTrue($chatMember->isKicked());
        $this->assertFalse($chatMember->canSpeak());
    }
}
