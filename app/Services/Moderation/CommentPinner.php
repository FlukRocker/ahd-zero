<?php

namespace App\Services\Moderation;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pin/unpin top-level comments. Max {@see self::LIMIT} pinned per thread
 * (commentable); a 4th pin is refused, nothing is auto-unpinned. Controllers
 * reach Mongo only through here (and CommentModerator) so tests can fake it.
 */
class CommentPinner
{
    public const LIMIT = 3;

    /**
     * @return array{ok: bool, status: int, message: string}
     */
    public function pin(Comment $comment): array
    {
        if ($comment->parent_id !== null) {
            return $this->result(false, 422, 'Only top-level comments can be pinned');
        }

        if ($comment->deleted_by !== null) {
            return $this->result(false, 422, 'Cannot pin a deleted comment');
        }

        if ($comment->pinned_at !== null) {
            return $this->result(true, 200, 'Already pinned');
        }

        if ($this->pinnedCount($comment) >= self::LIMIT) {
            return $this->result(false, 422, 'pin limit');
        }

        $comment->update(['pinned_at' => now()]);

        return $this->result(true, 200, 'Pinned');
    }

    /**
     * @return array{ok: bool, status: int, message: string}
     */
    public function unpin(Comment $comment): array
    {
        $comment->update(['pinned_at' => null]);

        return $this->result(true, 200, 'Unpinned');
    }

    public function pinnedCount(Comment $comment): int
    {
        return $this->threadPinned($comment->commentable_type, (int) $comment->commentable_id)->count();
    }

    /**
     * Pinned top-level comments of a thread, oldest pin first.
     *
     * @return Collection<int, Comment>
     */
    public function pinnedFor(string $site, string $commentableType, int $commentableId): Collection
    {
        return Comment::query()
            ->where('site', $site)
            ->where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->whereNull('parent_id')
            ->whereNull('deleted_by')
            ->whereNotNull('pinned_at')
            ->orderBy('pinned_at')
            ->limit(self::LIMIT)
            ->get();
    }

    private function threadPinned(string $type, int $id): \Illuminate\Database\Eloquent\Builder
    {
        return Comment::query()
            ->where('site', (string) config('app.site_key'))
            ->where('commentable_type', $type)
            ->where('commentable_id', $id)
            ->whereNull('parent_id')
            ->whereNull('deleted_by')
            ->whereNotNull('pinned_at');
    }

    /**
     * @return array{ok: bool, status: int, message: string}
     */
    private function result(bool $ok, int $status, string $message): array
    {
        return ['ok' => $ok, 'status' => $status, 'message' => $message];
    }
}
