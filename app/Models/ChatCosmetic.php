<?php

namespace App\Models;

use App\Enums\CosmeticType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-authored cosmetic. Members never create these — see
 * App\Support\CosmeticStyle for why that is a security boundary.
 *
 * @property int $id
 * @property CosmeticType $type
 * @property string $name
 * @property string $slug
 * @property array<string, mixed> $payload
 * @property int $price
 * @property bool $active
 */
class ChatCosmetic extends Model
{
    protected $table = 'chat_cosmetics';

    protected $fillable = ['type', 'name', 'slug', 'payload', 'price', 'active', 'sort'];

    protected $casts = [
        'type' => CosmeticType::class,
        'payload' => 'array',
        'price' => 'integer',
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    /**
     * @return HasMany<ChatCosmeticMember, $this>
     */
    public function owners(): HasMany
    {
        return $this->hasMany(ChatCosmeticMember::class, 'cosmetic_id');
    }
}
