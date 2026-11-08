<?php

namespace App\Broadcasting;

use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Member;

/**
 * Who may appear in the online roster.
 *
 * A class rather than a closure in routes/channels.php so it can be tested
 * directly: the test suite runs with BROADCAST_CONNECTION=null, and the
 * null broadcaster never invokes channel callbacks — an HTTP test of this
 * rule would pass no matter what the rule said.
 */
class RosterChannel
{
    /**
     * @return array<string, string>|false
     */
    public function join(Member $member, ChatRoom $room): array|false
    {
        if (! $room->active) {
            return false;
        }

        $chatMember = ChatMember::query()->find($member->id);

        if ($chatMember !== null && ($chatMember->isBanned() || $chatMember->isKicked())) {
            return false;
        }

        return [
            'uuid' => $member->uuid,
            'name' => $member->name,
            'rank' => $chatMember?->rank->value ?? 'member',
        ];
    }
}
