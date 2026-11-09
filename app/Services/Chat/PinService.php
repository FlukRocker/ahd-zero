<?php

namespace App\Services\Chat;

use App\Events\Chat\MessagePinned;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Support\Chat\ChatPins;

/**
 * Pin and unpin, shared by the in-room menu and the dashboard so both log and
 * broadcast the same way. One pin per room: a new pin replaces the old one.
 */
class PinService
{
    public function __construct(private readonly ChatService $chat) {}

    public function pin(ChatMessage $message, ?int $actorMemberId, ?int $adminId): void
    {
        $roomId = (int) $message->room_id;

        ChatPins::set($roomId, (int) $message->id);
        $this->log('pin_message', $message->member_id, $actorMemberId, $adminId, $message->id);

        $message->loadMissing('member:id,uuid,name,avatar');
        MessagePinned::dispatch($roomId, $this->chat->record($message));
    }

    /** Clears the room's pin, but only if it is still $onlyMessageId (a stale menu must not unpin a newer pin). */
    public function unpin(int $roomId, ?int $actorMemberId, ?int $adminId, ?int $onlyMessageId = null): void
    {
        $messageId = ChatPins::get($roomId);

        if ($messageId === null || ($onlyMessageId !== null && $onlyMessageId !== $messageId)) {
            return;
        }

        ChatPins::clear($roomId);
        $this->log('unpin_message', ChatMessage::withTrashed()->whereKey($messageId)->value('member_id'), $actorMemberId, $adminId, $messageId);

        MessagePinned::dispatch($roomId, null);
    }

    /** A deleted message must not stay pinned. Silent: the delete is the audited action. */
    public function forgetMessage(ChatMessage $message): void
    {
        $roomId = (int) $message->room_id;

        if (ChatPins::get($roomId) === (int) $message->id) {
            ChatPins::clear($roomId);
            MessagePinned::dispatch($roomId, null);
        }
    }

    private function log(string $action, mixed $targetMemberId, ?int $actorMemberId, ?int $adminId, int $messageId): void
    {
        ChatModerationLog::create([
            'actor_member_id' => $actorMemberId,
            'admin_id' => $adminId,
            'target_member_id' => $targetMemberId,
            'action' => $action,
            'meta' => ['message_id' => $messageId],
        ]);
    }
}
