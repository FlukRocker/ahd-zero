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
 * Every balance path first ensures the chat_members row exists, as its own
 * autocommit write OUTSIDE the money transaction, then locks it inside.
 * A member who has never spoken has no row, and `SELECT ... FOR UPDATE` on a
 * missing row locks nothing useful (gap lock, deadlock-prone); inserting inside
 * the transaction is no better, because INSERT IGNORE on an existing row takes
 * a shared lock on the duplicate key, so two concurrent buys both hold S and
 * then deadlock upgrading to X. Committing the insert first leaves nothing held.
 */
class StoreService
{
    /** Ensure the row exists (autocommit, no lock held afterwards). */
    private function ensureWallet(int $memberId): void
    {
        ChatMember::query()->insertOrIgnore([
            'member_id' => $memberId,
            'rank' => 'member',
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Lock the (now existing) row. Must be called inside the transaction. */
    private function lockWallet(int $memberId): ChatMember
    {
        /** @var ChatMember $wallet */
        $wallet = ChatMember::query()->whereKey($memberId)->lockForUpdate()->firstOrFail();

        return $wallet;
    }

    /**
     * Must not be called inside an open transaction: it opens its own transaction
     * and relies on that being the outermost one, so the wallet row lock is released at its
     * commit; nested, the lock would be held until the caller finishes.
     */
    public function buy(int $memberId, ChatCosmetic $cosmetic): void
    {
        $this->ensureWallet($memberId);

        DB::transaction(function () use ($memberId, $cosmetic): void {
            $wallet = $this->lockWallet($memberId);

            // Re-read under the lock: the route-bound model may be stale, so
            // active/price/type must come from the row as it is now.
            // The row lock also serialises buyers of a limited-stock item.
            $cosmetic = ChatCosmetic::query()->whereKey($cosmetic->id)->lockForUpdate()->firstOrFail();

            if (! $cosmetic->active) {
                throw new StoreException('That item is not available.');
            }

            if (($cosmetic->payload['show_only'] ?? false) === true) {
                throw new StoreException('That item is not for sale.');
            }

            $stock = $cosmetic->payload['stock'] ?? null;

            if (is_int($stock) && $stock < 1) {
                throw new StoreException('Sold out.');
            }

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

            if (is_int($stock)) {
                $payload = $cosmetic->payload;
                $payload['stock'] = $stock - 1;
                $cosmetic->update(['payload' => $payload]);
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
        }, 3);
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

    /**
     * Must not be called inside an open transaction: it opens its own transaction
     * and relies on that being the outermost one, so the wallet row lock is released at its
     * commit; nested, the lock would be held until the caller finishes.
     */
    public function grantCoins(int $memberId, int $amount, int $adminId, ?string $note = null): void
    {
        if ($amount < 1) {
            throw new StoreException('A grant must be a positive number of coins.');
        }

        $this->ensureWallet($memberId);

        DB::transaction(function () use ($memberId, $amount, $adminId, $note): void {
            $wallet = $this->lockWallet($memberId);
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
        }, 3);
    }

    /**
     * Take coins back. Same lock discipline as grantCoins (call outside a
     * transaction). The ledger reason column is an ENUM in production, so this
     * reuses 'admin_grant' with a negative delta and says what it is in the note.
     */
    public function debitCoins(int $memberId, int $amount, int $adminId, ?string $note = null): void
    {
        if ($amount < 1) {
            throw new StoreException('A deduction must be a positive number of coins.');
        }

        $this->ensureWallet($memberId);

        DB::transaction(function () use ($memberId, $amount, $adminId, $note): void {
            $wallet = $this->lockWallet($memberId);

            if ($wallet->balance < $amount) {
                throw new StoreException('The member only has '.$wallet->balance.' coins.');
            }

            $balance = $wallet->balance - $amount;
            $wallet->update(['balance' => $balance]);

            ChatTransaction::create([
                'member_id' => $memberId,
                'delta' => -$amount,
                'balance_after' => $balance,
                'reason' => 'admin_grant',
                'admin_id' => $adminId,
                'note' => mb_substr('Taken back'.($note !== null && $note !== '' ? ': '.$note : ''), 0, 255),
            ]);

            ChatModerationLog::create([
                'admin_id' => $adminId,
                'target_member_id' => $memberId,
                'action' => 'grant_coins',
                'meta' => ['amount' => -$amount, 'note' => $note, 'op' => 'take_coins'],
            ]);
        }, 3);
    }

    /** Remove an owned cosmetic. The log action column is an ENUM, so it reuses 'grant_cosmetic'. */
    public function revokeCosmetic(int $memberId, ChatCosmetic $cosmetic, int $adminId): bool
    {
        return DB::transaction(function () use ($memberId, $cosmetic, $adminId): bool {
            $deleted = ChatCosmeticMember::query()
                ->where('member_id', $memberId)
                ->where('cosmetic_id', $cosmetic->id)
                ->delete() > 0;

            if ($deleted) {
                ChatModerationLog::create([
                    'admin_id' => $adminId,
                    'target_member_id' => $memberId,
                    'action' => 'grant_cosmetic',
                    'meta' => ['cosmetic_id' => $cosmetic->id, 'op' => 'revoke_cosmetic'],
                ]);
            }

            return $deleted;
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
