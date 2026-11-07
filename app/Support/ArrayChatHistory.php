<?php

namespace App\Support;

use App\Contracts\ChatHistory;

/**
 * In-memory history for tests. `throws: true` simulates Redis being down so
 * the database-fallback path is exercised rather than assumed.
 */
class ArrayChatHistory implements ChatHistory
{
    /** @var array<int, array<string, mixed>> */
    private array $messages = [];

    public function __construct(private readonly bool $throws = false) {}

    public function push(array $message): void
    {
        // Mirrors LPUSHX: a cleared list stays cleared until replace().
        if ($this->throws || $this->messages === []) {
            return;
        }

        $this->messages[] = $message;
        $this->messages = array_slice($this->messages, -self::LIMIT);
    }

    public function replace(array $messages): void
    {
        if ($this->throws) {
            return;
        }

        $this->messages = array_slice($messages, -self::LIMIT);
    }

    public function recent(int $limit): ?array
    {
        if ($this->throws || $this->messages === []) {
            return null;
        }

        return array_slice($this->messages, -$limit);
    }

    public function forget(): void
    {
        $this->messages = [];
    }
}
