<?php

namespace Tests\Unit\Chat;

use App\Events\Chat\MemberSanctioned;
use App\Events\Chat\MessageDeleted;
use App\Models\ChatMessage;
use App\Models\Member;
use App\Services\Chat\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard: nothing broadcast on the public chat.room.* channels may
 * carry the internal member id or an email address.
 */
class BroadcastPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_message_record_has_exactly_the_public_key_set(): void
    {
        $member = Member::create(['name' => 'Rin', 'email' => 'rin@example.test', 'password' => 'password123!']);
        $message = ChatMessage::create(['member_id' => $member->id, 'body' => 'hi']);

        $keys = array_keys($this->app->make(ChatService::class)->record($message));
        sort($keys);

        $this->assertSame(['at', 'avatar', 'body', 'id', 'name', 'parts', 'rank', 'reply', 'style', 'uuid'], $keys);
    }

    public function test_member_sanctioned_goes_to_each_room_with_only_uuid_and_action(): void
    {
        $event = new MemberSanctioned([1, 2], '11111111-2222-3333-4444-555555555555', 'ban');

        $this->assertSame(['chat.room.1', 'chat.room.2'], array_map(fn ($c) => $c->name, $event->broadcastOn()));
        $keys = array_keys($event->broadcastWith());
        sort($keys);
        $this->assertSame(['action', 'uuid'], $keys);
    }

    public function test_message_deleted_goes_to_its_room_with_only_the_message_id(): void
    {
        $event = new MessageDeleted(3, 42);

        $this->assertSame('chat.room.3', $event->broadcastOn()->name);
        $this->assertSame(['id'], array_keys($event->broadcastWith()));
    }
}
