<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit row for every moderator and admin action. Exactly one of
 * actor_member_id (in-room moderator) / admin_id (dashboard) is set.
 *
 * @property int $id
 * @property string $action
 * @property array<string, mixed>|null $meta
 */
class ChatModerationLog extends Model
{
    protected $table = 'chat_moderation_log';

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_member_id', 'admin_id', 'target_member_id', 'action', 'meta',
    ];

    protected $casts = ['meta' => 'array'];
}
