<?php

namespace App\Support;

/**
 * Strips what a message body must never carry.
 *
 * C0/C1 control characters do nothing visible but survive escaping, and the
 * Unicode bidi overrides (U+202A–U+202E, U+2066–U+2069) let a sender reorder
 * how everyone else's client renders the line — a classic way to make a URL
 * read as something it is not. Zero-width characters (U+200B–U+200D, U+2060,
 * U+FEFF) are removed so a URL cannot be split to dodge the link rule, and
 * NFKC folds fullwidth lookalikes (ｈｔｔｐｓ) to plain ASCII before any check runs.
 */
class ChatText
{
    public static function clean(string $body): string
    {
        $withoutControls = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{80}-\x{9F}]/u', '', $body) ?? '';
        $withoutBidi = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $withoutControls) ?? '';
        $withoutZeroWidth = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $withoutBidi) ?? '';

        if (class_exists(\Normalizer::class)) {
            $withoutZeroWidth = \Normalizer::normalize($withoutZeroWidth, \Normalizer::FORM_KC) ?: $withoutZeroWidth;
        }

        return trim($withoutZeroWidth);
    }
}
