<?php

namespace App\Contracts;

/**
 * The recent-messages cache.
 *
 * An interface rather than a Redis call because tests must not need a live
 * Redis, and because "the cache is unavailable" has to be a path we can
 * exercise rather than assume. recent() returns null — not [] — when it has
 * nothing to offer, so the caller can tell "empty room" from "ask the database".
 */
interface ChatHistory
{
    /** Newest-last cap. */
    public const LIMIT = 200;

    /**
     * @param  array<string, mixed>  $message
     */
    public function push(array $message): void;

    /**
     * @return array<int, array<string, mixed>>|null Newest last, or null if unavailable.
     */
    public function recent(int $limit): ?array;

    /**
     * Overwrite the whole list with a rebuilt one.
     *
     * Needed because push() must not resurrect a key that was deliberately
     * cleared — otherwise the next single message becomes the entire
     * history and the room shows one line instead of fifty.
     *
     * @param  array<int, array<string, mixed>>  $messages  Oldest first.
     */
    public function replace(array $messages): void;

    /** Drop everything, so the next read rebuilds from the database. */
    public function forget(): void;
}
