<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inventory row: this member owns this cosmetic.
 *
 * The unique index on (member_id, cosmetic_id) is what makes a double-clicked
 * purchase a no-op instead of a double charge.
 *
 * @property int $id
 * @property int $member_id
 * @property int $cosmetic_id
 * @property bool $equipped
 * @property string $source
 */
class ChatCosmeticMember extends Model
{
    protected $table = 'chat_cosmetic_member';

    public $timestamps = false;

    protected $fillable = ['member_id', 'cosmetic_id', 'acquired_at', 'source', 'equipped'];

    protected $casts = [
        'acquired_at' => 'datetime',
        'equipped' => 'boolean',
    ];

    /**
     * @return BelongsTo<ChatCosmetic, $this>
     */
    public function cosmetic(): BelongsTo
    {
        return $this->belongsTo(ChatCosmetic::class, 'cosmetic_id');
    }
}
