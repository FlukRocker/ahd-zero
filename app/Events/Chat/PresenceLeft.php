<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Members whose heartbeat ran out: drop them from open online lists.
 * Carries only what the online list already shows (no ids, no email).
 */
class PresenceLeft implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $uuids) {}

    public function broadcastOn(): Channel
    {
        return new Channel('chat.presence');
    }

    public function broadcastAs(): string
    {
        return 'presence.left';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['uuids' => $this->uuids];
    }
}
