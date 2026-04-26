<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $_id
 * @property string $user_id
 * @property string $comment_id
 * @property string $type
 * @property string $from_user_name
 * @property string|null $from_user_avatar
 * @property string $message
 * @property bool $read
 * @property string $site
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CommentNotification extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'comment_notifications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'comment_id',
        'type',
        'from_user_name',
        'from_user_avatar',
        'message',
        'read',
        'site',
    ];
}
