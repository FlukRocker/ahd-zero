<?php

namespace App\Policies;

use App\Enums\ChatRank;

/**
 * Every moderation decision in the chat, in one class.
 *
 * The rule is: an actor may only act on a STRICTLY LOWER rank. Equal rank is
 * refused, which is what stops a mod gagging a mod. Both entry points — the
 * in-room POST /chat/moderate and the dashboard — call these methods rather
 * than re-deriving the comparison, so there is one place to get it wrong.
 */
class ChatPolicy
{
    public function canActOn(ChatRank $actor, ChatRank $target): bool
    {
        return $actor->outranks($target);
    }

    public function canGag(ChatRank $actor): bool
    {
        return $actor->level() >= ChatRank::Mod->level();
    }

    public function canKick(ChatRank $actor): bool
    {
        return $actor->level() >= ChatRank::Mod->level();
    }

    public function canBan(ChatRank $actor): bool
    {
        return $actor->level() >= ChatRank::Owner->level();
    }

    /**
     * Promoting is capped one rung below the actor: an owner makes mods, a
     * main owner makes owners. Nobody mints their own rank. Only owner and above
     * may change ranks at all. Callers must also canActOn() the target for demotions.
     */
    public function canSetRank(ChatRank $actor, ChatRank $newRank): bool
    {
        return $actor->level() >= ChatRank::Owner->level() && $actor->outranks($newRank);
    }

    /**
     * Deleting is judged on the MESSAGE AUTHOR's rank, with one addition: you
     * may always delete your own message.
     */
    public function canDeleteMessage(ChatRank $actor, ChatRank $author, bool $isOwnMessage): bool
    {
        return $isOwnMessage || $this->canActOn($actor, $author);
    }
}
