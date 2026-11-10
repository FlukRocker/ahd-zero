<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A member's look changed (equipped, unequipped, revoked or the cosmetic was edited): open chat boxes
 * restyle their lines and their online-list entry in place.
 * Carries only what the online list already shows (no ids, no email).
 */
class MemberRestyled implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly string $uuid,
        public readonly array $style,
        public readonly ?string $pawn,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('chat.presence');
    }

    public function broadcastAs(): string
    {
        return 'member.restyled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['uuid' => $this->uuid, 'style' => $this->style, 'pawn' => $this->pawn];
    }
}
