<?php

namespace App\Services;

use App\Contracts\AdminFeed;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\Comment;
use App\Models\Member;
use App\Services\Chat\ChatImage;
use Illuminate\Support\Str;
use Throwable;

class AdminFeeds implements AdminFeed
{
    /** Longest the dashboard waits on Mongo before giving up on the comment block. */
    private const COMMENTS_MAX_MS = 1500;

    private const BODY_LIMIT = 140;

    public function latestComments(int $limit = 8): ?array
    {
        try {
            $rows = $this->fetchComments($limit);
        } catch (Throwable) {
            return null;
        }

        return array_map(fn (array $row): array => [
            'id' => (string) $row['_id'],
            'user_name' => (string) ($row['user_name'] ?? ''),
            'body' => Str::limit((string) ($row['body'] ?? ''), self::BODY_LIMIT, ''),
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'deleted' => ($row['deleted_by'] ?? null) !== null,
        ], $rows);
    }

    public function recentChat(int $limit = 8): array
    {
        return ChatMessage::query()
            ->with(['member:id,name', 'room:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (ChatMessage $m): array => [
                'id' => (int) $m->id,
                'room' => $m->room?->name,
                'member_name' => $m->member?->name,
                'body' => Str::limit(ChatImage::split((string) $m->body)[0], self::BODY_LIMIT, ''),
                'image' => ($file = ChatImage::split((string) $m->body)[1]) === null ? null : ChatImage::url($file),
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function modLog(int $limit = 8): array
    {
        $logs = ChatModerationLog::query()->latest('created_at')->latest('id')->limit($limit)->get();
        $names = Member::query()->whereIn('id', $logs->pluck('target_member_id')->filter()->all())->pluck('name', 'id');

        return $logs->map(fn (ChatModerationLog $l): array => [
            'action' => $l->action,
            'target_member_name' => $names[$l->target_member_id] ?? null,
            'created_at' => $l->created_at?->toIso8601String(),
        ])->all();
    }

    /**
     * The only Mongo touch. Kept separate so tests can replace it.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchComments(int $limit): array
    {
        return Comment::query()
            ->where('site', (string) config('app.site_key'))
            ->orderByDesc('created_at')
            ->limit($limit)
            // options() lives on the Mongo query builder the Eloquent builder forwards to.
            ->options(['maxTimeMS' => self::COMMENTS_MAX_MS]) // @phpstan-ignore method.notFound
            ->get()
            ->map(fn (Comment $c): array => [
                '_id' => $c->getKey(),
                'user_name' => $c->user_name,
                'body' => $c->body,
                'created_at' => $c->created_at?->toIso8601String(),
                'deleted_by' => $c->deleted_by,
            ])
            ->all();
    }
}
