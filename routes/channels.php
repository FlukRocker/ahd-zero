<?php

use App\Broadcasting\RosterChannel;
use Illuminate\Support\Facades\Broadcast;

/**
 * `chat.lobby` is public and needs no entry here — messages, deletions and
 * sanction notices go to everyone, including guests, because the room is
 * world-readable.
 *
 * `chat.roster` is the presence channel: the online list. It is named
 * `chat.roster` rather than reusing `chat.lobby` so one channel name never has
 * two meanings.
 *
 * Note what this cannot do: authorisation runs at SUBSCRIBE time, so banning
 * someone does not evict a socket that is already connected. They keep reading
 * until they reconnect. That is acceptable only because the room is public;
 * the controls that matter are here and on the POST.
 */
// The class string, NOT [RosterChannel::class, 'join']. Laravel's
// normalizeChannelHandlerToCallable() treats an array as an ordinary
// callable — which would be a static call of an instance method — and
// only resolves a bare class string out of the container to call join().
// No test covers this line, because the null broadcaster never invokes
// channel callbacks; it would break in production only.
Broadcast::channel('chat.roster', RosterChannel::class, ['guards' => ['member']]);
