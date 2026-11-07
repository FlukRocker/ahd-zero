<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only coin ledger. Every balance change writes one of these in the
 * same DB transaction as the balance itself, so the ledger always
 * reconstructs the balance.
 *
 * @property int $id
 * @property int $member_id
 * @property int $delta
 * @property int $balance_after
 * @property string $reason
 */
class ChatTransaction extends Model
{
    protected $table = 'chat_transactions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'member_id', 'delta', 'balance_after', 'reason', 'cosmetic_id', 'admin_id', 'note',
    ];

    protected $casts = [
        'delta' => 'integer',
        'balance_after' => 'integer',
    ];
}
