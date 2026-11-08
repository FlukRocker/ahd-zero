<?php

namespace Tests\Unit;

use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\Member;
use App\Services\AdminFeeds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AdminFeedsTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): Member
    {
        return Member::forceCreate([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::slug($name).'@example.test',
            'password' => 'x',
        ]);
    }

    public function test_recent_chat_returns_newest_first_and_skips_deleted(): void
    {
        $rin = $this->member('Rin');
        $old = ChatMessage::create(['member_id' => $rin->id, 'body' => 'old']);
        $old->forceFill(['created_at' => Carbon::now()->subMinutes(5)])->save();
        ChatMessage::create(['member_id' => $rin->id, 'body' => 'new']);
        ChatMessage::create(['member_id' => $rin->id, 'body' => 'gone'])->delete();

        $rows = (new AdminFeeds)->recentChat();

        $this->assertSame(['new', 'old'], array_column($rows, 'body'));
        $this->assertSame('Rin', $rows[0]['member_name']);
        $this->assertSame('Lobby', $rows[0]['room']);
    }

    public function test_mod_log_returns_newest_first(): void
    {
        $kai = $this->member('Kai');
        $first = ChatModerationLog::create(['target_member_id' => $kai->id, 'action' => 'gag']);
        $first->forceFill(['created_at' => Carbon::now()->subMinutes(5)])->save();
        ChatModerationLog::create(['target_member_id' => $kai->id, 'action' => 'ban']);

        $rows = (new AdminFeeds)->modLog();

        $this->assertSame(['ban', 'gag'], array_column($rows, 'action'));
        $this->assertSame('Kai', $rows[0]['target_member_name']);
    }

    public function test_latest_comments_is_null_when_mongo_fails(): void
    {
        $feeds = new class extends AdminFeeds
        {
            protected function fetchComments(int $limit): array
            {
                throw new RuntimeException('mongo down');
            }
        };

        $this->assertNull($feeds->latestComments());
    }

    public function test_latest_comments_shapes_rows_and_truncates_long_bodies(): void
    {
        $feeds = new class extends AdminFeeds
        {
            protected function fetchComments(int $limit): array
            {
                return [['_id' => 'abc', 'user_name' => 'Mio', 'body' => str_repeat('x', 300), 'created_at' => '2026-11-09T10:00:00Z', 'deleted_by' => null]];
            }
        };

        $row = $feeds->latestComments()[0];

        $this->assertSame(['id', 'user_name', 'body', 'created_at', 'deleted'], array_keys($row));
        $this->assertSame('abc', $row['id']);
        $this->assertSame(140, mb_strlen($row['body']));
        $this->assertFalse($row['deleted']);
    }
}
