<?php

namespace App\Services\Chat;

use App\Enums\ChatRank;
use App\Models\ChatMember;
use App\Models\Member;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Who is on the site right now, on any page.
 *
 * Every page a logged-in member has open sends a heartbeat (see
 * resources/js/presence.js); this keeps the last-seen time per member. A
 * member counts as online while their last heartbeat is younger than TTL.
 * It replaces the per-room presence channel for the online list, which only
 * knew about people who had a chat box open.
 *
 * Stored in the cache as one small map (uuid => id, name, last seen) behind a
 * lock. Losing a write to contention is harmless: the next heartbeat repairs it.
 */
class PresenceService
{
    public function __construct(private readonly MessageRenderer $renderer) {}

    /** Seconds a heartbeat keeps a member online. Clients ping every 45s. */
    public const TTL = 100;

    private const KEY = 'chat:online';

    public function touch(Member $member): void
    {
        $chat = ChatMember::query()->find($member->id);

        if ($chat !== null && ($chat->isBanned() || $chat->isKicked())) {
            return;
        }

        $now = now()->getTimestamp();

        try {
            Cache::lock(self::KEY.':lock', 5)->block(1, function () use ($member, $now): void {
                $map = $this->fresh(Cache::get(self::KEY, []), $now);
                $map[$member->uuid] = ['id' => $member->id, 'name' => $member->name, 'at' => $now];
                Cache::put(self::KEY, $map, self::TTL * 3);
            });
        } catch (LockTimeoutException) {
            // Someone else holds the lock; the next heartbeat will land.
        }
    }

    /**
     * Online members (unsorted; the client orders them). Banned and kicked members are left out.
     *
     * @return list<array{uuid: string, name: string, rank: string, style: array<string, string>}>
     */
    public function online(): array
    {
        $map = $this->fresh(Cache::get(self::KEY, []), now()->getTimestamp());

        if ($map === []) {
            return [];
        }

        $chat = ChatMember::query()->whereIn('member_id', array_column($map, 'id'))->get()->keyBy('member_id');
        $this->renderer->preload(array_column($map, 'id'));
        $people = [];

        foreach ($map as $uuid => $entry) {
            $row = $chat->get($entry['id']);

            if ($row !== null && ($row->isBanned() || $row->isKicked())) {
                continue;
            }

            $people[] = [
                'uuid' => (string) $uuid,
                'name' => (string) $entry['name'],
                'rank' => ($row?->rank ?? ChatRank::Member)->value,
                'style' => $this->renderer->styleFor($entry['id']),
            ];
        }

        return $people;
    }

    /**
     * @param  array<string, array{id: int, name: string, at: int}>  $map
     * @return array<string, array{id: int, name: string, at: int}>
     */
    private function fresh(array $map, int $now): array
    {
        return array_filter($map, fn (array $entry): bool => $entry['at'] > $now - self::TTL);
    }
}
