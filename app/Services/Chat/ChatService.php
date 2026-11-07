<?php

namespace App\Services\Chat;

use App\Contracts\ChatHistory;
use App\Models\ChatMessage;

/**
 * Reading and writing the room's message list.
 *
 * The cache is an optimisation, never the truth: every read that misses falls
 * through to chat_messages, and a delete drops the whole key rather than
 * trying to surgically remove one JSON blob from a Redis list — rebuilding 200
 * rows is cheap and leaves no way for a deleted message to come back.
 */
class ChatService
{
    public function __construct(
        private readonly ChatHistory $history,
        private readonly MessageRenderer $renderer,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 50): array
    {
        $cached = $this->history->recent($limit);

        if ($cached !== null) {
            return $cached;
        }

        $rebuilt = ChatMessage::query()
            ->with('member:id,uuid,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(ChatHistory::LIMIT)
            ->get()
            ->reverse()
            ->values();

        // Two queries for every author at once, not two per message.
        $this->renderer->preload($rebuilt->pluck('member_id')->unique()->map(fn ($id): int => (int) $id)->all());

        $rebuilt = $rebuilt->map(fn (ChatMessage $message): array => $this->record($message))
            ->all();

        // Warm the cache back up, or push() (LPUSHX) stays a no-op forever
        // and every request pays for the rebuild.
        $this->history->replace($rebuilt);

        return array_slice($rebuilt, -$limit);
    }

    /**
     * The broadcast/render shape for one message. Built in one place so the
     * socket payload and the server-rendered page can never drift apart.
     *
     * @return array<string, mixed>
     */
    public function record(ChatMessage $message): array
    {
        $memberId = (int) $message->member_id;

        return [
            'id' => $message->id,
            'uuid' => $message->member?->uuid,
            'name' => $message->member?->name,
            'body' => $message->body,
            'parts' => $this->renderer->parts($message->body, $this->renderer->smiliesFor($memberId)),
            'style' => $this->renderer->styleFor($memberId),
            'at' => $message->created_at?->toIso8601String(),
            'rank' => $this->renderer->rankFor($memberId),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function remember(array $record): void
    {
        $this->history->push($record);
    }

    public function forgetHistory(): void
    {
        $this->history->forget();
    }
}
