<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShouldBroadcastNow, not ShouldBroadcast: production sets
 * QUEUE_CONNECTION=redis and runs no queue worker by default, so a queued
 * broadcast would sit in Redis forever while every Event::fake test passed.
 *
 * ShouldRescue because broadcasting now happens INSIDE the request. Without
 * it, a Reverb daemon that is down throws after the message is already
 * saved: the sender sees "not sent", retries, and posts it twice. The spec
 * says a broadcast failure is logged, never fatal.
 *
 * The payload is structured data, never HTML — the client builds text nodes
 * from it and never touches innerHTML.
 */
class MessageSent implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $message
     */
    public function __construct(public readonly array $message) {}

    public function broadcastOn(): Channel
    {
        return new Channel('chat.lobby');
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->message;
    }
}
