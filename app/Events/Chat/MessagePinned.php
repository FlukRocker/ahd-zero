<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessagePinned implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $pinned  the pinned message record, or null when the pin was cleared
     */
    public function __construct(
        public readonly int $roomId,
        public readonly ?array $pinned,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("chat.room.{$this->roomId}");
    }

    public function broadcastAs(): string
    {
        return 'message.pinned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['pinned' => $this->pinned];
    }
}
