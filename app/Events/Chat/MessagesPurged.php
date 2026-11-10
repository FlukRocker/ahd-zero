<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Many messages removed at once: everything one member wrote in the room
 * (`uuid` set), or the whole room (`uuid` null). Open chat boxes drop the lines.
 */
class MessagesPurged implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $roomId,
        public readonly ?string $uuid,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("chat.room.{$this->roomId}");
    }

    public function broadcastAs(): string
    {
        return 'messages.purged';
    }

    /**
     * @return array<string, string|null>
     */
    public function broadcastWith(): array
    {
        return ['uuid' => $this->uuid];
    }
}
