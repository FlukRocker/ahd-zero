<?php

namespace App\Services\Moderation;

use App\Models\Comment;
use App\Models\Member;
use App\Models\Report;

/**
 * Soft-delete and ban logic for comments, shared by the admin comment
 * controller, the author delete and the reports queue. Controllers reach
 * comments only through here so tests can swap it (no Mongo in PHPUnit).
 */
class CommentModerator
{
    public function __construct(private readonly ReportResolver $reports) {}

    public function find(string $commentId): Comment
    {
        return Comment::query()->findOrFail($commentId);
    }

    public function delete(Comment $comment, ?int $adminId, string $deletedBy = 'admin'): void
    {
        $comment->update($this->tombstone($deletedBy));

        $this->reports->resolveTarget(Report::TYPE_COMMENT, (string) $comment->getKey(), Report::STATUS_ACTIONED, $adminId);
    }

    public function deleteAndBan(Comment $comment, ?string $reason, bool $deleteAll, ?int $adminId): void
    {
        $this->delete($comment, $adminId);

        if ($comment->user_id === null) {
            return;
        }

        if ($deleteAll) {
            Comment::query()
                ->where('site', (string) config('app.site_key'))
                ->where('user_id', $comment->user_id)
                ->whereNull('deleted_by')
                ->update($this->tombstone('admin'));
        }

        Member::query()->where('uuid', $comment->user_id)->first()?->update([
            'banned_at' => now(),
            'ban_reason' => $reason ?? 'ถูกแบนจากระบบความคิดเห็น',
        ]);
    }

    /** @return array<string, mixed> */
    private function tombstone(string $deletedBy): array
    {
        return [
            'body' => '',
            'deleted_by' => $deletedBy,
            'deleted_at' => now()->toISOString(),
            'reactions' => [],
            'pinned_at' => null,
        ];
    }
}
