<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line of chat. Soft-deleted: a moderator removing a message must not
 * destroy the evidence of what was removed.
 *
 * @property int $id
 * @property int $member_id
 * @property int $room_id
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $deleted_by
 * @property int|null $deleted_by_admin_id
 */
class ChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'chat_messages';

    public const UPDATED_AT = null;

    protected $fillable = ['member_id', 'body', 'room_id'];

    /**
     * @return BelongsTo<ChatRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }
}
