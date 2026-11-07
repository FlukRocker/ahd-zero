<?php

namespace App\Models;

use App\Enums\ChatRank;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One participant's chat state: rank, sanctions and coin balance.
 *
 * Kept off `members` because this app does not own that table, and because a
 * site ban and a chat ban are different things — see isBanned().
 *
 * @property int $member_id
 * @property ChatRank $rank
 * @property int $balance
 * @property \Illuminate\Support\Carbon|null $gagged_until
 * @property \Illuminate\Support\Carbon|null $kicked_until
 * @property \Illuminate\Support\Carbon|null $banned_at
 * @property \Illuminate\Support\Carbon|null $banned_until
 * @property string|null $ban_reason
 */
class ChatMember extends Model
{
    protected $table = 'chat_members';

    protected $primaryKey = 'member_id';

    public $incrementing = false;

    protected $fillable = [
        'member_id', 'rank', 'balance',
        'gagged_until', 'kicked_until', 'banned_at', 'banned_until', 'ban_reason',
    ];

    protected $casts = [
        'rank' => ChatRank::class,
        'balance' => 'integer',
        'gagged_until' => 'datetime',
        'kicked_until' => 'datetime',
        'banned_at' => 'datetime',
        'banned_until' => 'datetime',
    ];

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    /**
     * The one definition of an active ban. A timed ban that has run out is not
     * an active ban, and nothing sweeps the table to make that true — the
     * predicate is evaluated per request, so expiry needs no cron.
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null
            && ($this->banned_until === null || $this->banned_until->isFuture());
    }

    public function isGagged(): bool
    {
        return $this->gagged_until !== null && $this->gagged_until->isFuture();
    }

    public function isKicked(): bool
    {
        return $this->kicked_until !== null && $this->kicked_until->isFuture();
    }

    /** May this member speak right now? */
    public function canSpeak(): bool
    {
        return ! $this->isBanned() && ! $this->isGagged() && ! $this->isKicked();
    }
}
