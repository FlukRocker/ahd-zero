<?php

namespace App\Services\Chat;

use App\Enums\ChatRank;
use App\Events\Chat\MemberSanctioned;
use App\Models\ChatMember;
use App\Models\ChatModerationLog;
use App\Models\ChatRoom;
use Illuminate\Support\Facades\DB;

/**
 * Applies a moderation action and records it.
 *
 * Authorisation is NOT done here — the caller asks ChatPolicy first, because
 * the dashboard acts as an admin with no rank and the room acts as a member
 * with one. What this class guarantees is that no action is applied without a
 * log row (state change and log insert share one transaction) and a
 * broadcast (dispatched only after that transaction commits).
 */
class ModerationService
{
    public function gag(ChatMember $target, int $minutes, ?int $actorId, ?int $adminId): void
    {
        $this->apply(fn () => $target->update(['gagged_until' => now()->addMinutes($minutes)]), 'gag', $target, $actorId, $adminId, ['minutes' => $minutes]);
    }

    public function ungag(ChatMember $target, ?int $actorId, ?int $adminId): void
    {
        $this->apply(fn () => $target->update(['gagged_until' => null]), 'ungag', $target, $actorId, $adminId);
    }

    public function kick(ChatMember $target, ?int $actorId, ?int $adminId): void
    {
        $this->apply(fn () => $target->update(['kicked_until' => now()->addSeconds(60)]), 'kick', $target, $actorId, $adminId);
    }

    public function ban(ChatMember $target, ?int $minutes, ?string $reason, ?int $actorId, ?int $adminId): void
    {
        $this->apply(fn () => $target->update([
            'banned_at' => now(),
            'banned_until' => $minutes === null ? null : now()->addMinutes($minutes),
            'ban_reason' => $reason,
        ]), 'ban', $target, $actorId, $adminId, ['minutes' => $minutes, 'reason' => $reason]);
    }

    public function unban(ChatMember $target, ?int $actorId, ?int $adminId): void
    {
        $this->apply(fn () => $target->update(['banned_at' => null, 'banned_until' => null, 'ban_reason' => null]), 'unban', $target, $actorId, $adminId);
    }

    public function setRank(ChatMember $target, ChatRank $rank, ?int $actorId, ?int $adminId): void
    {
        $this->apply(fn () => $target->update(['rank' => $rank]), 'rank_change', $target, $actorId, $adminId, ['rank' => $rank->value]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function apply(\Closure $change, string $action, ChatMember $target, ?int $actorId, ?int $adminId, array $meta = []): void
    {
        DB::transaction(function () use ($change, $action, $target, $actorId, $adminId, $meta): void {
            $change();

            ChatModerationLog::create([
                'actor_member_id' => $actorId,
                'admin_id' => $adminId,
                'target_member_id' => $target->member_id,
                'action' => $action,
                'meta' => $meta === [] ? null : $meta,
            ]);
        });

        $uuid = $target->member?->uuid;

        if ($uuid !== null) {
            MemberSanctioned::dispatch(
                array_map('intval', ChatRoom::active()->pluck('id')->all()),
                $uuid,
                $action,
            );
        }
    }
}
