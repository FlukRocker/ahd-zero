<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A member's report of a comment or a chat message.
 *
 * target_id is a string because a comment id is a Mongo ObjectId while a chat
 * message id is an integer. The table is created by hand on prod (migration
 * lives in ahd-admin).
 *
 * @property int $id
 * @property int $member_id
 * @property string $target_type
 * @property string $target_id
 * @property string $reason
 * @property string|null $note
 * @property string $status
 * @property int|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class Report extends Model
{
    public const TYPE_COMMENT = 'comment';

    public const TYPE_CHAT = 'chat_message';

    public const REASONS = ['spam', 'abuse', 'other'];

    public const STATUS_OPEN = 'open';

    public const STATUS_ACTIONED = 'actioned';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'member_id', 'target_type', 'target_id', 'reason', 'note', 'status', 'resolved_by', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];
}
