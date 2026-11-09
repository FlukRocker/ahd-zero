<?php

namespace App\Support;

use App\Contracts\ReportTargetResolver;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Member;
use App\Models\Report;
use App\Services\Chat\ChatImage;
use Illuminate\Support\Str;

class EloquentReportTargets implements ReportTargetResolver
{
    public function find(string $type, string $id): ?ReportTarget
    {
        return match ($type) {
            Report::TYPE_CHAT => $this->chatMessage($id),
            Report::TYPE_COMMENT => $this->comment($id),
            default => null,
        };
    }

    private function chatMessage(string $id): ?ReportTarget
    {
        if (! ctype_digit($id)) {
            return null;
        }

        // withTrashed so a deleted message reads as "unavailable", not unknown.
        $message = ChatMessage::query()->withTrashed()->find((int) $id);

        if ($message === null) {
            return null;
        }

        [$text, $image] = ChatImage::split((string) $message->body);

        return new ReportTarget(
            Report::TYPE_CHAT,
            (string) $message->id,
            (int) $message->member_id,
            Str::limit($text, 140, ''),
            ! $message->trashed(),
            $image === null || $message->trashed() ? null : ChatImage::url($image),
        );
    }

    private function comment(string $id): ?ReportTarget
    {
        $comment = Comment::query()->find($id);

        if ($comment === null) {
            return null;
        }

        // The Mongo collection is shared with another site.
        $available = $comment->site === (string) config('app.site_key') && $comment->deleted_by === null;
        $author = $comment->user_id === null ? null : Member::query()->where('uuid', $comment->user_id)->value('id');

        return new ReportTarget(
            Report::TYPE_COMMENT,
            (string) $comment->getKey(),
            $author === null ? null : (int) $author,
            Str::limit((string) $comment->body, 140, ''),
            $available,
        );
    }
}
