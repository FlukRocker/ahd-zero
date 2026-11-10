<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A member came online (first heartbeat after being away).
 * Carries only what the online list already shows (no ids, no email).
 */
class PresenceJoined implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $person) {}

    public function broadcastOn(): Channel
    {
        return new Channel('chat.presence');
    }

    public function broadcastAs(): string
    {
        return 'presence.joined';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['person' => $this->person];
    }
}
