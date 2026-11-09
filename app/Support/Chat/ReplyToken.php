<?php

namespace App\Support\Chat;

/**
 * A reply is a server-written "[re=ID] " prefix on the stored body. Members
 * cannot type one (ChatController rejects it), so a token always points at a
 * message the server checked existed in the same room.
 */
class ReplyToken
{
    private const PATTERN = '~^\[re=(\d{1,12})\] ?~';

    public static function token(int $messageId): string
    {
        return '[re='.$messageId.'] ';
    }

    /**
     * @return array{0: int|null, 1: string} [replied-to id, body without the token]
     */
    public static function split(string $body): array
    {
        if (preg_match(self::PATTERN, $body, $m) !== 1) {
            return [null, $body];
        }

        return [(int) $m[1], substr($body, strlen($m[0]))];
    }
}
