<?php

namespace App\Services\Chat;

use App\Enums\ChatRank;
use App\Enums\CosmeticType;
use App\Models\ChatCosmetic;
use App\Models\ChatCosmeticMember;
use App\Models\ChatMember;
use App\Support\CosmeticStyle;
use Illuminate\Support\Collection;

/**
 * Turns a stored body into typed segments, and a member's equipped items into
 * inline custom properties.
 *
 * The split happens here rather than in the browser because the client is not
 * allowed to touch innerHTML — it cannot find-and-replace a code with an <img>
 * without parsing markup. Given segments, it creates a text node or an image
 * element per segment and never parses anything.
 *
 * Ownership is the gate: a code the SENDER does not own stays literal text, so
 * nobody gets to use a smiley they did not buy by typing its code.
 *
 * Every style value and asset URL leaves through CosmeticStyle, which
 * re-validates the stored payload on each render.
 *
 * Lookups are memoised per instance (resolved fresh per request — nothing binds
 * this as a singleton), and preload() resolves many members in one query so a
 * history rebuild does not cost two queries per message.
 */
class MessageRenderer
{
    /** @var array<int, array<string, string>> */
    private array $smileyCache = [];

    /** @var array<int, array<string, string>> */
    private array $styleCache = [];

    /** @var array<int, string> */
    private array $rankCache = [];

    /**
     * @param  array<string, string>  $smilies  code => asset url, for codes the sender owns
     * @return array<int, array{t: string, v: string}>
     */
    public function parts(string $body, array $smilies): array
    {
        if ($smilies === []) {
            return [['t' => 'text', 'v' => $body]];
        }

        $codes = array_map(
            static fn (string $code): string => preg_quote($code, '/'),
            array_keys($smilies),
        );

        $pieces = preg_split(
            '/('.implode('|', $codes).')/',
            $body,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [$body];

        $parts = [];

        foreach ($pieces as $piece) {
            $parts[] = isset($smilies[$piece])
                ? ['t' => 'img', 'v' => $smilies[$piece]]
                : ['t' => 'text', 'v' => $piece];
        }

        return $parts;
    }

    /**
     * Resolves several members' inventories in ONE query and fills the memo.
     *
     * @param  array<int, int>  $memberIds
     */
    public function preload(array $memberIds): void
    {
        $missing = array_values(array_unique(array_filter(
            $memberIds,
            fn (int $id): bool => ! isset($this->smileyCache[$id]),
        )));

        if ($missing === []) {
            return;
        }

        $rows = ChatCosmeticMember::query()
            ->whereIn('member_id', $missing)
            ->with('cosmetic')
            ->get()
            ->groupBy('member_id');

        // Ranks ride the same preload so a history rebuild stays one extra
        // query for all authors; a member with no chat_members row is a plain
        // member.
        $ranks = ChatMember::query()
            ->whereIn('member_id', $missing)
            ->get(['member_id', 'rank'])
            ->mapWithKeys(fn (ChatMember $row): array => [(int) $row->member_id => $row->rank->value]);

        foreach ($missing as $id) {
            $this->rankCache[$id] = $ranks->get($id, ChatRank::Member->value);
            $this->fill($id, $rows->get($id, new Collection));
        }
    }

    /**
     * The smiley codes this member may actually use, as code => asset url.
     * An asset that no longer exists on disk is dropped, so a stale row cannot
     * produce a broken image.
     *
     * @return array<string, string>
     */
    public function smiliesFor(int $memberId): array
    {
        $this->preload([$memberId]);

        return $this->smileyCache[$memberId];
    }

    public function rankFor(int $memberId): string
    {
        $this->preload([$memberId]);

        return $this->rankCache[$memberId];
    }

    /**
     * @return array<string, string>
     */
    public function styleFor(int $memberId): array
    {
        $this->preload([$memberId]);

        return $this->styleCache[$memberId];
    }

    /**
     * @param  Collection<int, ChatCosmeticMember>  $rows
     */
    private function fill(int $memberId, Collection $rows): void
    {
        $smilies = [];
        $style = [];

        foreach ($rows as $row) {
            $cosmetic = $row->cosmetic;

            if ($cosmetic === null) {
                continue;
            }

            if ($row->equipped) {
                $style += CosmeticStyle::for($cosmetic);
            }

            // Owned smilies keep rendering after an admin deactivates them: inventory stays intact in v1.
            if ($cosmetic->type === CosmeticType::Smiley) {
                $entry = $this->smiley($cosmetic);

                if ($entry !== null) {
                    $smilies[$entry[0]] = $entry[1];
                }
            }
        }

        $this->smileyCache[$memberId] = $smilies;
        $this->styleCache[$memberId] = $style;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function smiley(ChatCosmetic $cosmetic): ?array
    {
        /** @var mixed $payload */
        $payload = $cosmetic->payload;

        if (! is_array($payload)) {
            return null;
        }

        $code = $payload['code'] ?? null;
        $asset = $payload['asset'] ?? null;

        if (! is_string($code) || ! is_string($asset) || ! CosmeticStyle::isValidSmileyCode($code)) {
            return null;
        }

        $url = CosmeticStyle::assetUrl(CosmeticType::Pawn, $asset);

        return $url === null ? null : [$code, $url];
    }
}
