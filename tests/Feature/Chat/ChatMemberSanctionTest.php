<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMember;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChatMemberSanctionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function frozen(): Carbon
    {
        $now = Carbon::parse('2026-01-01 12:00:00');
        Carbon::setTestNow($now);

        return $now;
    }

    public function test_expiry_boundaries_for_gag_kick_and_ban(): void
    {
        $now = $this->frozen();
        $cases = [
            'gag' => [fn ($t) => ['gagged_until' => $t], 'isGagged'],
            'kick' => [fn ($t) => ['kicked_until' => $t], 'isKicked'],
            'ban' => [fn ($t) => ['banned_at' => $now->copy()->subDay(), 'banned_until' => $t], 'isBanned'],
        ];
        foreach ($cases as $name => [$attrs, $predicate]) {
            $this->assertFalse($this->chatMember($attrs($now->copy()->subSecond()))->$predicate(), "$name -1s");
            $this->assertTrue($this->chatMember($attrs($now->copy()->addSecond()))->$predicate(), "$name +1s");
            $this->assertFalse($this->chatMember($attrs($now->copy()))->$predicate(), "$name exact");
        }
    }

    public function test_a_permanent_ban_blocks_speaking_regardless_of_time(): void
    {
        $now = $this->frozen();
        $chatMember = $this->chatMember(['banned_at' => $now->copy()->subYears(5), 'banned_until' => null]);

        $this->assertTrue($chatMember->isBanned());
        $this->assertFalse($chatMember->canSpeak());
        Carbon::setTestNow($now->copy()->addYears(50));
        $this->assertTrue($chatMember->isBanned());
        $this->assertFalse($chatMember->canSpeak());
    }

    public function test_expired_ban_and_kick_allow_speaking_and_live_gag_blocks(): void
    {
        $now = $this->frozen();
        $this->assertTrue($this->chatMember(['banned_at' => $now->copy()->subDay(), 'banned_until' => $now->copy()->subSecond()])->canSpeak());
        $this->assertTrue($this->chatMember(['kicked_until' => $now->copy()->subSecond()])->canSpeak());
        $this->assertFalse($this->chatMember(['gagged_until' => $now->copy()->addSecond()])->canSpeak());
    }

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
