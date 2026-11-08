<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A topic room. Created by admins; every member and guest can read every
 * active room. The `lobby` row is the default and is never deactivated.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $topic
 * @property int $sort
 * @property bool $active
 */
class ChatRoom extends Model
{
    public const LOBBY_SLUG = 'lobby';

    protected $table = 'chat_rooms';

    protected $fillable = ['slug', 'name', 'topic', 'sort', 'active'];

    protected $casts = [
        'sort' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * @param  Builder<ChatRoom>  $query
     * @return Builder<ChatRoom>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public static function lobby(): self
    {
        return static::query()->where('slug', self::LOBBY_SLUG)->firstOrFail();
    }

    /**
     * @return Collection<int, ChatRoom>
     */
    public static function activeOrdered(): Collection
    {
        return static::query()->active()->orderBy('sort')->orderBy('id')->get();
    }
}
