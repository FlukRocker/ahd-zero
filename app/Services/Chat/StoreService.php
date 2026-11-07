<?php

namespace App\Services\Chat;

use App\Enums\CosmeticType;
use App\Exceptions\NotOwnedException;
use App\Exceptions\StoreException;
use App\Models\ChatCosmetic;
use App\Models\ChatCosmeticMember;
use App\Models\ChatMember;
use App\Models\ChatModerationLog;
use App\Models\ChatTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Coins and inventory.
 *
 * Every balance path starts with insertOrIgnore: a member who has never spoken
 * has no chat_members row, and `SELECT ... FOR UPDATE` on a row that does not
 * exist locks nothing — two concurrent first-time writes would both insert and
 * collide on the primary key.
 */
class StoreService
{
    /** Ensure the row exists, then take a write lock on it. */
    public function wallet(int $memberId): ChatMember
    {
        DB::table('chat_members')->insertOrIgnore([
            'member_id' => $memberId,
            'rank' => 'member',
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var ChatMember $wallet */
        $wallet = ChatMember::query()->whereKey($memberId)->lockForUpdate()->firstOrFail();

        return $wallet;
    }

    public function buy(int $memberId, ChatCosmetic $cosmetic): void
    {
        if (! $cosmetic->active) {
            throw new StoreException('That item is not available.');
        }

        DB::transaction(function () use ($memberId, $cosmetic): void {
            $wallet = $this->wallet($memberId);

            $owned = ChatCosmeticMember::query()
                ->where('member_id', $memberId)
                ->where('cosmetic_id', $cosmetic->id)
                ->exists();

            if ($owned) {
                throw new StoreException('You already own that item.');
            }

            if ($wallet->balance < $cosmetic->price) {
                throw new StoreException('You do not have enough coins.');
            }

            try {
                ChatCosmeticMember::create([
                    'member_id' => $memberId,
                    'cosmetic_id' => $cosmetic->id,
                    'acquired_at' => now(),
                    'source' => 'purchase',
                    'equipped' => false,
                ]);
            } catch (QueryException $e) {
                // The unique index is the real guard; the check above only
                // narrows the window. 23000 = integrity constraint violation.
                if ($e->getCode() === '23000') {
                    throw new StoreException('You already own that item.');
                }

                throw $e;
            }

            $balance = $wallet->balance - $cosmetic->price;
            $wallet->update(['balance' => $balance]);

            ChatTransaction::create([
                'member_id' => $memberId,
                'delta' => -$cosmetic->price,
                'balance_after' => $balance,
                'reason' => 'purchase',
                'cosmetic_id' => $cosmetic->id,
            ]);
        });
    }

    public function equip(int $memberId, ChatCosmetic $cosmetic): void
    {
        $slot = $cosmetic->type->slot();

        if ($slot === null) {
            throw new StoreException('That item is always available and is not equipped.');
        }

        DB::transaction(function () use ($memberId, $cosmetic, $slot): void {
            $owned = ChatCosmeticMember::query()
                ->where('member_id', $memberId)
                ->where('cosmetic_id', $cosmetic->id)
                ->lockForUpdate()
                ->first();

            if ($owned === null) {
                throw new NotOwnedException('You do not own that item.');
            }

            $sameSlotTypes = array_values(array_filter(
                CosmeticType::cases(),
                fn (CosmeticType $type): bool => $type->slot() === $slot,
            ));

            ChatCosmeticMember::query()
                ->where('member_id', $memberId)
                ->whereIn('cosmetic_id', ChatCosmetic::query()
                    ->whereIn('type', array_map(fn ($type) => $type->value, $sameSlotTypes))
                    ->pluck('id'))
                ->update(['equipped' => false]);

            $owned->update(['equipped' => true]);
        });
    }

    public function grantCoins(int $memberId, int $amount, int $adminId, ?string $note = null): void
    {
        if ($amount < 1) {
            throw new StoreException('A grant must be a positive number of coins.');
        }

        DB::transaction(function () use ($memberId, $amount, $adminId, $note): void {
            $wallet = $this->wallet($memberId);
            $balance = $wallet->balance + $amount;

            $wallet->update(['balance' => $balance]);

            ChatTransaction::create([
                'member_id' => $memberId,
                'delta' => $amount,
                'balance_after' => $balance,
                'reason' => 'admin_grant',
                'admin_id' => $adminId,
                'note' => $note,
            ]);

            ChatModerationLog::create([
                'admin_id' => $adminId,
                'target_member_id' => $memberId,
                'action' => 'grant_coins',
                'meta' => ['amount' => $amount, 'note' => $note],
            ]);
        });
    }

    public function grantCosmetic(int $memberId, ChatCosmetic $cosmetic, int $adminId): void
    {
        DB::transaction(function () use ($memberId, $cosmetic, $adminId): void {
            ChatCosmeticMember::query()->firstOrCreate(
                ['member_id' => $memberId, 'cosmetic_id' => $cosmetic->id],
                ['acquired_at' => now(), 'source' => 'grant', 'equipped' => false],
            );

            ChatModerationLog::create([
                'admin_id' => $adminId,
                'target_member_id' => $memberId,
                'action' => 'grant_cosmetic',
                'meta' => ['cosmetic_id' => $cosmetic->id],
            ]);
        });
    }
}
