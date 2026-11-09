<?php

namespace App\Services\Chat;

use App\Contracts\ChatHistory;
use App\Models\ChatMessage;
use App\Support\Chat\Bbcode;
use App\Support\Chat\ChatPins;
use App\Support\Chat\ReplyToken;
use App\Support\Chat\SafeUrl;

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

    /** @var array<int, array{id: int, name: ?string, text: ?string}> */
    private array $replyCache = [];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $roomId, int $limit = 50): array
    {
        $cached = $this->history->recent($roomId, $limit);

        if ($cached !== null) {
            return $cached;
        }

        $rebuilt = ChatMessage::query()
            ->where('room_id', $roomId)
            ->with('member:id,uuid,name,avatar')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(ChatHistory::LIMIT)
            ->get()
            ->reverse()
            ->values();

        // Three queries for every author at once (cosmetic ownership, cosmetics, rank), not per message.
        $this->renderer->preload($rebuilt->pluck('member_id')->unique()->map(fn ($id): int => (int) $id)->all());

        $this->preloadReplies($rebuilt->map(fn (ChatMessage $m): ?int => ReplyToken::split((string) $m->body)[0])->filter()->unique()->values()->all());

        $rebuilt = $rebuilt->map(fn (ChatMessage $message): array => $this->record($message))
            ->all();

        // Warm the cache back up, or push() (LPUSHX) stays a no-op forever
        // and every request pays for the rebuild.
        $this->history->replace($roomId, $rebuilt);

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
        [$replyId, $text] = ReplyToken::split((string) $message->body);

        return [
            'id' => $message->id,
            'uuid' => $message->member?->uuid,
            'name' => $message->member?->name,
            'avatar' => SafeUrl::https($message->member?->avatar, 1024),
            'body' => $text,
            'parts' => $this->renderer->parts($message->body, $this->renderer->smiliesFor($memberId)),
            'style' => $this->renderer->styleFor($memberId),
            'at' => $message->created_at?->toIso8601String(),
            'rank' => $this->renderer->rankFor($memberId),
            'reply' => $replyId === null ? null : $this->replyPreview($replyId),
        ];
    }

    /**
     * The smiley codes this member owns, as code => asset url, for the picker.
     *
     * @return array<string, string>
     */
    public function smiliesFor(int $memberId): array
    {
        return $this->renderer->smiliesFor($memberId);
    }

    /**
     * The room's pinned message as a normal record, or null when nothing is
     * pinned (or the pinned message is gone).
     *
     * @return array<string, mixed>|null
     */
    public function pinned(int $roomId): ?array
    {
        $id = ChatPins::get($roomId);

        if ($id === null) {
            return null;
        }

        $message = ChatMessage::query()->with('member:id,uuid,name,avatar')->where('room_id', $roomId)->find($id);

        return $message === null ? null : $this->record($message);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function preloadReplies(array $ids): void
    {
        $ids = array_values(array_filter($ids, fn (int $id): bool => ! isset($this->replyCache[$id])));

        if ($ids === []) {
            return;
        }

        $found = ChatMessage::query()->with('member:id,name')->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            $this->replyCache[$id] = $this->previewOf($id, $found->get($id));
        }
    }

    /**
     * @return array{id: int, name: ?string, text: ?string}
     */
    private function replyPreview(int $id): array
    {
        if (! isset($this->replyCache[$id])) {
            $this->replyCache[$id] = $this->previewOf($id, ChatMessage::query()->with('member:id,name')->find($id));
        }

        return $this->replyCache[$id];
    }

    /**
     * @return array{id: int, name: ?string, text: ?string}
     */
    private function previewOf(int $id, ?ChatMessage $original): array
    {
        if ($original === null) {
            return ['id' => $id, 'name' => null, 'text' => null];
        }

        $segments = Bbcode::parse(ReplyToken::split((string) $original->body)[1]);
        $text = trim(Bbcode::plainText($segments).(Bbcode::imageCount($segments) > 0 ? ' [รูป]' : ''));

        return ['id' => $id, 'name' => $original->member?->name, 'text' => mb_substr($text, 0, 80)];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function remember(int $roomId, array $record): void
    {
        $this->history->push($roomId, $record);
    }

    public function forgetHistory(int $roomId): void
    {
        $this->history->forget($roomId);
    }
}
