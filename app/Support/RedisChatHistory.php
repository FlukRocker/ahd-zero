<?php

namespace App\Support;

use App\Contracts\ChatHistory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * The Redis-list implementation.
 *
 * One key, written through the facade so it carries the app prefix — the
 * instance is shared with kurokami, lnw-anime and neko_miku, and this class
 * must never touch anything outside its own key.
 *
 * Every method swallows Throwable and degrades: a chat that is slow because
 * Redis is down beats a chat that 500s because Redis is down.
 */
class RedisChatHistory implements ChatHistory
{
    private const KEY = 'chat:lobby:history';

    public function push(array $message): void
    {
        $json = json_encode($message);

        try {
            // LPUSHX, not LPUSH: it does nothing when the key is absent.
            // After a delete or a Redis restart the key is gone, and a
            // plain LPUSH would recreate it holding exactly one message —
            // which recent() would then serve as the whole history. A miss
            // must stay a miss until replace() rebuilds it from MySQL.
            //
            // A pipeline batches the round trips; it is not MULTI/EXEC, so a
            // concurrent push can leave the list briefly over the cap. The
            // next push trims it back.
            $this->connection()->pipeline(function ($pipe) use ($json): void {
                $pipe->lpushx(self::KEY, $json);
                $pipe->ltrim(self::KEY, 0, self::LIMIT - 1);
            });
        } catch (Throwable $e) {
            Log::warning('Chat history push failed', ['error' => $e->getMessage()]);
        }
    }

    public function recent(int $limit): ?array
    {
        try {
            $raw = Redis::lrange(self::KEY, 0, $limit - 1);
        } catch (Throwable $e) {
            Log::warning('Chat history read failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $decoded = [];

        foreach (array_reverse($raw) as $json) {
            $row = json_decode((string) $json, true);

            if (is_array($row)) {
                $decoded[] = $row;
            }
        }

        return $decoded === [] ? null : $decoded;
    }

    public function replace(array $messages): void
    {
        try {
            // Pipelined for round trips only, not atomic: a concurrent push
            // may land between the del and the lpushes; the TTL below bounds
            // any resulting drift.
            $this->connection()->pipeline(function ($pipe) use ($messages): void {
                $pipe->del(self::KEY);

                // Oldest first, so the newest ends up at the head where
                // recent() expects it.
                foreach (array_slice($messages, -self::LIMIT) as $message) {
                    $pipe->lpush(self::KEY, json_encode($message));
                }

                // A rebuild can race a concurrent delete and write the
                // deleted row back. A TTL bounds how long a bad rebuild can
                // survive; chat_messages stays the only authority.
                $pipe->expire(self::KEY, 300);
            });
        } catch (Throwable $e) {
            Log::warning('Chat history rebuild failed', ['error' => $e->getMessage()]);
        }
    }

    // No native return type: under predis this is a PredisConnection. The
    // @var is a static-analysis hint only.
    private function connection()
    {
        /** @var PhpRedisConnection $connection */
        $connection = Redis::connection();

        return $connection;
    }

    public function forget(): void
    {
        try {
            Redis::del(self::KEY);
        } catch (Throwable $e) {
            Log::warning('Chat history clear failed', ['error' => $e->getMessage()]);
        }
    }
}
