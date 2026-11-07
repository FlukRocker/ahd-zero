<?php

namespace Tests\Feature\Chat;

use App\Contracts\ChatHistory;
use App\Models\ChatMessage;
use App\Models\Member;
use App\Services\Chat\ChatService;
use App\Support\ArrayChatHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatHistoryTest extends TestCase
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
        ]);
    }

    public function test_history_comes_from_the_cache_when_it_is_warm(): void
    {
        // replace(), not push(): push() mirrors LPUSHX and is a no-op on an
        // empty list, exactly as it is against a missing Redis key.
        $history = $this->app->make(ChatHistory::class);
        $history->replace([['id' => 1, 'body' => 'cached line', 'uuid' => $this->member->uuid]]);

        $lines = $this->app->make(ChatService::class)->history();

        $this->assertCount(1, $lines);
        $this->assertSame('cached line', $lines[0]['body']);
    }

    public function test_an_empty_cache_rebuilds_from_the_database_without_deleted_rows(): void
    {
        ChatMessage::create(['member_id' => $this->member->id, 'body' => 'kept']);
        ChatMessage::create(['member_id' => $this->member->id, 'body' => 'removed'])->delete();

        $lines = $this->app->make(ChatService::class)->history();

        $this->assertSame(['kept'], array_column($lines, 'body'));
    }

    /**
     * Review Focus #5. Redis being down must not take the page down with it.
     */
    public function test_a_throwing_cache_falls_back_to_the_database(): void
    {
        ChatMessage::create(['member_id' => $this->member->id, 'body' => 'still here']);
        $this->app->instance(ChatHistory::class, new ArrayChatHistory(throws: true));

        $lines = $this->app->make(ChatService::class)->history();

        $this->assertSame(['still here'], array_column($lines, 'body'));
    }

    /**
     * The bug this pins: push() must not resurrect a cleared key. If it
     * does, the message sent right after a moderator deletes something
     * becomes the entire history and the room drops to one line.
     */
    public function test_a_send_after_a_delete_does_not_shrink_the_history(): void
    {
        foreach (['one', 'two', 'three'] as $body) {
            ChatMessage::create(['member_id' => $this->member->id, 'body' => $body]);
        }

        $service = $this->app->make(ChatService::class);
        $service->history();                 // warms the cache
        $service->forgetHistory();           // a delete clears it

        $fresh = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'four']);
        $service->remember($service->record($fresh->load('member')));

        $this->assertSame(['one', 'two', 'three', 'four'], array_column($service->history(), 'body'));
    }

    /** Exercises the fake, not RedisChatHistory — LTRIM is not covered by any test. */
    public function test_pushing_past_the_cap_keeps_only_the_newest(): void
    {
        $history = new ArrayChatHistory;
        $history->replace([['id' => 0, 'body' => 'seed', 'uuid' => $this->member->uuid]]);

        foreach (range(1, 205) as $i) {
            $history->push(['id' => $i, 'body' => "line {$i}", 'uuid' => $this->member->uuid]);
        }

        $recent = $history->recent(500);

        $this->assertCount(200, $recent);
        $this->assertSame('line 205', end($recent)['body']);
    }

    public function test_a_throwing_cache_does_not_break_the_send_path(): void
    {
        $this->app->instance(ChatHistory::class, new ArrayChatHistory(throws: true));
        $service = $this->app->make(ChatService::class);

        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hi']);
        $service->remember($service->record($message->load('member')));
        $service->forgetHistory();

        $this->assertSame(['hi'], array_column($service->history(), 'body'));
    }

    public function test_rows_created_in_the_same_second_keep_insertion_order(): void
    {
        foreach (['a', 'b', 'c'] as $body) {
            ChatMessage::create(['member_id' => $this->member->id, 'body' => $body]);
        }

        $lines = $this->app->make(ChatService::class)->history();

        $this->assertSame(['a', 'b', 'c'], array_column($lines, 'body'));
    }
}
