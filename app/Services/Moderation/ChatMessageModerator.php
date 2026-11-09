<?php

namespace App\Services\Moderation;

use App\Events\Chat\MessageDeleted;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\Report;
use App\Services\Chat\ChatService;
use App\Services\Chat\PinService;

/**
 * Admin delete of a chat message, shared by the chat console and the reports
 * queue so both clear history, broadcast and close reports the same way.
 */
class ChatMessageModerator
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly ReportResolver $reports,
        private readonly PinService $pins,
    ) {}

    public function delete(ChatMessage $message, int $adminId): void
    {
        $message->deleted_by_admin_id = $adminId;
        $message->save();
        $message->delete();

        // Admins act through the web guard, not as members, so actor_member_id
        // stays null and admin_id carries the identity.
        ChatModerationLog::create([
            'actor_member_id' => null,
            'admin_id' => $adminId,
            'target_member_id' => $message->member_id,
            'action' => 'delete_message',
            'meta' => ['message_id' => $message->id],
        ]);

        $this->chat->forgetHistory((int) $message->room_id);
        $this->pins->forgetMessage($message);

        // The room has to hear about it too. Without this an admin delete
        // vanishes from the database but stays on every open screen until
        // the next reload.
        MessageDeleted::dispatch((int) $message->room_id, $message->id);

        $this->reports->resolveTarget(Report::TYPE_CHAT, (string) $message->id, Report::STATUS_ACTIONED, $adminId);
    }
}
