<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

/**
 * @property string $_id
 * @property string $body
 * @property string|null $user_id
 * @property string $user_name
 * @property string|null $user_avatar
 * @property string $commentable_type
 * @property int $commentable_id
 * @property bool $is_admin
 * @property string|null $deleted_by
 * @property string|null $parent_id
 * @property string $site
 * @property array<int, array{emoji: string, user_ids: array<int, string>}> $reactions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Comment extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'comments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'body',
        'user_id',
        'user_name',
        'user_avatar',
        'is_admin',
        'deleted_by',
        'commentable_type',
        'commentable_id',
        'parent_id',
        'reactions',
        // Site discriminator so kurokami + ahd can share one Mongo
        // `comments` collection without leaking each other's threads.
        'site',
    ];

    /**
     * @return HasMany<self, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', '_id');
    }
}
