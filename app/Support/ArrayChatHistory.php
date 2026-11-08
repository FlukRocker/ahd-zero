<?php

namespace App\Support;

use App\Contracts\ChatHistory;

/**
 * In-memory history for tests, one list per room. `throws: true` simulates
 * Redis being down so the database-fallback path is exercised rather than
 * assumed.
 */
class ArrayChatHistory implements ChatHistory
{
    /** @var array<int, array<int, array<string, mixed>>> */
    private array $rooms = [];

    public function __construct(private readonly bool $throws = false) {}

    public function push(int $roomId, array $message): void
    {
        // Mirrors LPUSHX: a cleared list stays cleared until replace().
        if ($this->throws || ($this->rooms[$roomId] ?? []) === []) {
            return;
        }

        $this->rooms[$roomId][] = $message;
        $this->rooms[$roomId] = array_slice($this->rooms[$roomId], -self::LIMIT);
    }

    public function replace(int $roomId, array $messages): void
    {
        if ($this->throws) {
            return;
        }

        $this->rooms[$roomId] = array_slice($messages, -self::LIMIT);
    }

    public function recent(int $roomId, int $limit): ?array
    {
        if ($this->throws || ($this->rooms[$roomId] ?? []) === []) {
            return null;
        }

        return array_slice($this->rooms[$roomId], -$limit);
    }

    public function forget(int $roomId): void
    {
        $this->rooms[$roomId] = [];
    }
}
