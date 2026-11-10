<?php

namespace App\Services\Chat;

use App\Enums\ChatRank;
use App\Events\Chat\MemberRestyled;
use App\Events\Chat\PresenceJoined;
use App\Events\Chat\PresenceLeft;
use App\Models\ChatCosmeticMember;
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
        $joined = false;
        $gone = [];

        try {
            Cache::lock(self::KEY.':lock', 5)->block(1, function () use ($member, $now, &$joined, &$gone): void {
                $stored = Cache::get(self::KEY, []);
                $map = $this->fresh($stored, $now);
                $gone = array_keys(array_diff_key($stored, $map));
                $joined = ! isset($map[$member->uuid]);
                $map[$member->uuid] = ['id' => $member->id, 'name' => $member->name, 'at' => $now];
                Cache::put(self::KEY, $map, self::TTL * 3);
            });
        } catch (LockTimeoutException) {
            // Someone else holds the lock; the next heartbeat will land.
            return;
        }

        // Push the change so open lists update at once. The poll stays the
        // source of truth, so a lost event only delays the update.
        if ($gone !== []) {
            PresenceLeft::dispatch(array_values(array_map('strval', $gone)));
        }

        if ($joined) {
            $this->renderer->preload([(int) $member->id]);
            PresenceJoined::dispatch($this->person((int) $member->id, $member->uuid, $member->name, $chat?->rank ?? ChatRank::Member));
        }
    }

    /**
     * Online members (unsorted; the client orders them). Banned and kicked members are left out.
     *
     * @return list<array{uuid: string, name: string, rank: string, style: array<string, string>, pawn: string|null, id?: int}>
     */
    public function online(bool $withIds = false): array
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

            $people[] = ($withIds ? ['id' => (int) $entry['id']] : []) + $this->person((int) $entry['id'], (string) $uuid, (string) $entry['name'], $row?->rank ?? ChatRank::Member);
        }

        return $people;
    }

    /** Tell open chat boxes that these members now look different. */
    public function announceStyle(int ...$memberIds): void
    {
        $members = Member::query()->whereIn('id', $memberIds)->get(['id', 'uuid']);

        foreach ($members as $member) {
            $style = $this->renderer->styleFor((int) $member->id);
            MemberRestyled::dispatch((string) $member->uuid, $style, $this->pawnOf($style));
        }
    }

    /** A cosmetic changed: restyle its owners (capped; the rest catch up on reload). */
    public function announceOwners(int $cosmeticId, int $cap = 200): void
    {
        $this->announceStyle(...ChatCosmeticMember::query()->where('cosmetic_id', $cosmeticId)->limit($cap)->pluck('member_id')->map(fn ($id) => (int) $id)->all());
    }

    /**
     * @param  array<string, string>  $style
     */
    private function pawnOf(array $style): ?string
    {
        return preg_match("#^url\('(/chat-assets/pawns/[a-z0-9-]{1,32}\.svg)'\)$#", $style['--chat-pawn'] ?? '', $m) === 1 ? $m[1] : null;
    }

    /**
     * @return array{uuid: string, name: string, rank: string, style: array<string, string>, pawn: string|null}
     */
    private function person(int $id, string $uuid, string $name, ChatRank $rank): array
    {
        $style = $this->renderer->styleFor($id);

        return [
            'uuid' => $uuid,
            'name' => $name,
            'rank' => $rank->value,
            'style' => $style,
            'pawn' => $this->pawnOf($style),
        ];
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
