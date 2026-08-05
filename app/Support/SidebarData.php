<?php

namespace App\Support;

use App\Models\Tag;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Cache;

/**
 * Supplies the two sidebar blocks. Knows nothing about Blade — the component
 * renders whatever this returns, and this can be tested without rendering.
 */
class SidebarData
{
    /**
     * Tab key => day window. `null` means all time.
     *
     * Keys are deliberately non-numeric: PHP silently casts '7' and '30' array
     * keys to integers, which makes strict string comparisons downstream fail.
     *
     * @var array<string, int|null>
     */
    public const WINDOWS = ['7d' => 7, '30d' => 30, 'all' => null];

    /** Popular is a rolling aggregate that barely moves; matches the homepage rail. */
    private const POPULAR_TTL = 600;

    /** The genre list is effectively static — re-querying it per request is waste. */
    private const GENRES_TTL = 3600;

    private const POPULAR_LIMIT = 10;

    public function __construct(private AnalyticsService $analytics) {}

    /**
     * @return list<array{slug: string, label: string}>
     */
    public function genres(): array
    {
        return Cache::remember('sidebar:genres', self::GENRES_TTL, fn (): array => Tag::query()
            ->where('type', 'genre')
            ->orderBy('order_column')
            ->orderBy('name')
            ->get(['slug', 'name', 'name_th'])
            ->map(fn (Tag $tag): array => [
                'slug' => (string) $tag->slug,
                // Thai name where the import provided one; the English name is
                // the fallback rather than a blank chip.
                'label' => (string) ($tag->name_th ?: $tag->name),
            ])
            ->all());
    }

    /**
     * All three windows, keyed by tab. Every window is rendered server-side so
     * the tabs switch without a fetch.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function popular(): array
    {
        $out = [];

        foreach (self::WINDOWS as $key => $days) {
            $out[$key] = Cache::remember(
                "sidebar:popular:{$key}",
                self::POPULAR_TTL,
                fn (): array => CardPresenter::collection(
                    $this->analytics->getTrendingCards($days, self::POPULAR_LIMIT)
                ),
            );
        }

        return $out;
    }

    /**
     * True when every window came back empty — the caller uses this to drop
     * the block rather than print a heading over nothing.
     *
     * @param  array<string, list<array<string, mixed>>>  $popular
     */
    public function hasPopular(array $popular): bool
    {
        foreach ($popular as $items) {
            if ($items !== []) {
                return true;
            }
        }

        return false;
    }
}
