<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_lobby_row_exists_in_test_schema(): void
    {
        $this->assertSame('lobby', ChatRoom::lobby()->slug);
    }

    public function test_new_message_defaults_to_lobby(): void
    {
        $member = Member::forceCreate([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'a',
            'email' => 'a@example.test',
            'password' => 'x',
        ]);

        $message = ChatMessage::create(['member_id' => $member->id, 'body' => 'hi']);

        $this->assertSame(ChatRoom::lobby()->id, (int) $message->fresh()->room_id);
    }

    public function test_active_ordered_excludes_inactive_and_sorts(): void
    {
        ChatRoom::create(['slug' => 'b-room', 'name' => 'B', 'sort' => 20, 'active' => true]);
        ChatRoom::create(['slug' => 'a-room', 'name' => 'A', 'sort' => 10, 'active' => true]);
        ChatRoom::create(['slug' => 'off', 'name' => 'Off', 'sort' => 1, 'active' => false]);

        $slugs = ChatRoom::activeOrdered()->pluck('slug')->all();

        $this->assertSame(['lobby', 'a-room', 'b-room'], $slugs);
    }
}
