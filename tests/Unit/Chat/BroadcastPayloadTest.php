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
 * Regression guard: nothing broadcast on the public chat.lobby channel may
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

        $this->assertSame(['at', 'body', 'id', 'name', 'parts', 'rank', 'style', 'uuid'], $keys);
    }

    public function test_member_sanctioned_goes_to_the_lobby_with_only_uuid_and_action(): void
    {
        $event = new MemberSanctioned('11111111-2222-3333-4444-555555555555', 'ban');

        $this->assertSame('chat.lobby', $event->broadcastOn()->name);
        $keys = array_keys($event->broadcastWith());
        sort($keys);
        $this->assertSame(['action', 'uuid'], $keys);
    }

    public function test_message_deleted_goes_to_the_lobby_with_only_the_message_id(): void
    {
        $event = new MessageDeleted(42);

        $this->assertSame('chat.lobby', $event->broadcastOn()->name);
        $this->assertSame(['id'], array_keys($event->broadcastWith()));
    }
}
