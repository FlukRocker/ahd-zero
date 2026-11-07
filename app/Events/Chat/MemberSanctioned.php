<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the room someone was gagged, kicked, banned or re-ranked.
 *
 * The client reacts by dropping its presence subscription when the subject is
 * itself. That is a UX nicety, NOT a security control — the server-side truth
 * is the POST check and the presence authorisation. A socket already connected
 * keeps reading until it reconnects, which is acceptable only because /chat is
 * world-readable anyway.
 */
class MemberSanctioned implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $uuid,
        public readonly string $action,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('chat.lobby');
    }

    public function broadcastAs(): string
    {
        return 'member.sanctioned';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['uuid' => $this->uuid, 'action' => $this->action];
    }
}
