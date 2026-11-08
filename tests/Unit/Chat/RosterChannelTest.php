<?php

namespace Tests\Unit\Chat;

use App\Broadcasting\RosterChannel;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterChannelTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
            'email_verified_at' => now(),
        ]);
    }

    public function test_a_member_joins_as_a_uuid_and_never_a_numeric_id(): void
    {
        $payload = (new RosterChannel)->join($this->member, ChatRoom::lobby());

        $this->assertIsArray($payload);
        $this->assertSame($this->member->uuid, $payload['uuid']);
        $this->assertArrayNotHasKey('id', $payload);
        $this->assertSame('member', $payload['rank']);
    }

    public function test_a_banned_member_is_refused(): void
    {
        ChatMember::create(['member_id' => $this->member->id, 'banned_at' => now()]);

        $this->assertFalse((new RosterChannel)->join($this->member, ChatRoom::lobby()));
    }

    public function test_a_kicked_member_is_refused_until_the_cooldown_passes(): void
    {
        $row = ChatMember::create([
            'member_id' => $this->member->id,
            'kicked_until' => now()->addSeconds(60),
        ]);

        $this->assertFalse((new RosterChannel)->join($this->member, ChatRoom::lobby()));

        $row->update(['kicked_until' => now()->subSecond()]);

        $this->assertIsArray((new RosterChannel)->join($this->member->fresh(), ChatRoom::lobby()));
    }

    public function test_the_broadcast_identifier_is_the_uuid(): void
    {
        // Presence channels put getAuthIdentifierForBroadcasting() in
        // channel_data.user_id, which every other subscriber receives. The
        // default is members.id, so without the override the roster leaks the
        // internal bigint to everyone in the room.
        $this->assertSame($this->member->uuid, $this->member->getAuthIdentifierForBroadcasting());
    }
}
