<?php

namespace App\Enums;

/**
 * The chat rank ladder. The whole moderation model is one rule — an actor may
 * only act on a strictly lower rank — so comparison lives here rather than
 * being re-derived at each call site.
 */
enum ChatRank: string
{
    case Member = 'member';
    case Mod = 'mod';
    case Owner = 'owner';
    case MainOwner = 'main_owner';

    public function level(): int
    {
        return match ($this) {
            self::Member => 1,
            self::Mod => 2,
            self::Owner => 3,
            self::MainOwner => 4,
        };
    }

    public function outranks(self $other): bool
    {
        return $this->level() > $other->level();
    }
}
