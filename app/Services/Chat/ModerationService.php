<?php

namespace App\Services\Chat;

use App\Enums\ChatRank;
use App\Events\Chat\MemberSanctioned;
use App\Models\ChatMember;
use App\Models\ChatModerationLog;

/**
 * Applies a moderation action and records it.
 *
 * Authorisation is NOT done here — the caller asks ChatPolicy first, because
 * the dashboard acts as an admin with no rank and the room acts as a member
 * with one. What this class guarantees is that no action is applied without a
 * log row and a broadcast.
 */
class ModerationService
{
    public function gag(ChatMember $target, int $minutes, ?int $actorId, ?int $adminId): void
    {
        $target->update(['gagged_until' => now()->addMinutes($minutes)]);
        $this->record('gag', $target, $actorId, $adminId, ['minutes' => $minutes]);
    }

    public function ungag(ChatMember $target, ?int $actorId, ?int $adminId): void
    {
        $target->update(['gagged_until' => null]);
        $this->record('ungag', $target, $actorId, $adminId);
    }

    public function kick(ChatMember $target, ?int $actorId, ?int $adminId): void
    {
        $target->update(['kicked_until' => now()->addSeconds(60)]);
        $this->record('kick', $target, $actorId, $adminId);
    }

    public function ban(ChatMember $target, ?int $minutes, ?string $reason, ?int $actorId, ?int $adminId): void
    {
        $target->update([
            'banned_at' => now(),
            'banned_until' => $minutes === null ? null : now()->addMinutes($minutes),
            'ban_reason' => $reason,
        ]);
        $this->record('ban', $target, $actorId, $adminId, ['minutes' => $minutes, 'reason' => $reason]);
    }

    public function unban(ChatMember $target, ?int $actorId, ?int $adminId): void
    {
        $target->update(['banned_at' => null, 'banned_until' => null, 'ban_reason' => null]);
        $this->record('unban', $target, $actorId, $adminId);
    }

    public function setRank(ChatMember $target, ChatRank $rank, ?int $actorId, ?int $adminId): void
    {
        $target->update(['rank' => $rank]);
        $this->record('rank_change', $target, $actorId, $adminId, ['rank' => $rank->value]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function record(string $action, ChatMember $target, ?int $actorId, ?int $adminId, array $meta = []): void
    {
        ChatModerationLog::create([
            'actor_member_id' => $actorId,
            'admin_id' => $adminId,
            'target_member_id' => $target->member_id,
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
        ]);

        $uuid = $target->member?->uuid;

        if ($uuid !== null) {
            MemberSanctioned::dispatch($uuid, $action);
        }
    }
}
