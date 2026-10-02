<?php

namespace Tests\Unit\Services;

use App\Services\TrendingNetwork;
use PHPUnit\Framework\TestCase;

class TrendingNetworkTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /**
     * @param  array<int, int>  $items
     * @return array{total: int, computed_at: int, items: array<int, int>}
     */
    private function snapshot(array $items, ?int $total = null, int $age = 60): array
    {
        return ['total' => $total ?? array_sum($items), 'computed_at' => self::NOW - $age, 'items' => $items];
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array{mode: string, network_weight: float, days: int, cold_start_min_views: int, refresh_minutes: int, source_weights: array<string, float>, pinned: list<int>, excluded: list<int>, refresh_requested_at: int|null}
     */
    private function settings(array $override = []): array
    {
        return TrendingNetwork::resolveSettings(['cold_start_min_views' => 0], $override);
    }

    /**
     * @param  array<string, array{total: int, computed_at: int, items: array<int, int>}>  $snapshots
     * @param  array<string, mixed>  $settings
     * @return list<int>
     */
    private function ids(array $snapshots, array $settings = [], int $limit = 10): array
    {
        return array_column(TrendingNetwork::blend('hana', $this->settings($settings), $snapshots, $limit, self::NOW), 'cat_id');
    }

    public function test_own_mode_ignores_the_network(): void
    {
        $snapshots = [
            'hana' => $this->snapshot([1 => 5, 2 => 3]),
            'ahd' => $this->snapshot([9 => 1000, 2 => 900]),
        ];

        $this->assertSame([1, 2], $this->ids($snapshots, ['mode' => 'own']));
    }

    public function test_network_mode_ranks_by_the_other_sites_only(): void
    {
        $snapshots = [
            'hana' => $this->snapshot([1 => 50]),
            'ahd' => $this->snapshot([9 => 1000, 2 => 900]),
        ];

        $this->assertSame([9, 2], $this->ids($snapshots, ['mode' => 'network']));
    }

    public function test_blend_compares_shares_not_raw_counts(): void
    {
        // ahd has a thousand times hana's traffic. By raw counts ahd's
        // favourite would win any blend; by share, hana's own favourite
        // (100% of its views) beats it at a 0.3 network weight.
        $snapshots = [
            'hana' => $this->snapshot([1 => 10]),
            'ahd' => $this->snapshot([9 => 10000]),
        ];

        $ranked = TrendingNetwork::blend('hana', $this->settings(['mode' => 'blend', 'network_weight' => 0.3]), $snapshots, 10, self::NOW);

        $this->assertSame([1, 9], array_column($ranked, 'cat_id'));
        $this->assertEqualsWithDelta(0.7, $ranked[0]['score'], 1e-9);
        $this->assertEqualsWithDelta(0.3, $ranked[1]['score'], 1e-9);
    }

    public function test_an_anime_popular_on_both_sides_rises(): void
    {
        $snapshots = [
            'hana' => $this->snapshot([1 => 6, 2 => 4]),
            'ahd' => $this->snapshot([2 => 100]),
        ];

        // hana alone: 1 (0.6) over 2 (0.4). With half weight on the network,
        // 2 scores 0.5 * 0.4 + 0.5 * 1.0 = 0.7 against 1's 0.3.
        $this->assertSame([2, 1], $this->ids($snapshots, ['network_weight' => 0.5]));
    }

    public function test_cold_start_ranks_purely_by_the_network(): void
    {
        $snapshots = [
            'hana' => $this->snapshot([1 => 3]),
            'ahd' => $this->snapshot([9 => 100, 8 => 50]),
        ];

        $this->assertSame([9, 8], $this->ids($snapshots, ['cold_start_min_views' => 10]));
    }

    public function test_cold_start_does_not_override_own_mode(): void
    {
        $snapshots = [
            'hana' => $this->snapshot([1 => 3]),
            'ahd' => $this->snapshot([9 => 100]),
        ];

        $this->assertSame([1], $this->ids($snapshots, ['mode' => 'own', 'cold_start_min_views' => 10]));
    }

    public function test_without_any_source_it_falls_back_to_own_traffic(): void
    {
        $snapshots = ['hana' => $this->snapshot([1 => 3, 2 => 1])];

        $this->assertSame([1, 2], $this->ids($snapshots, ['mode' => 'network']));
    }

    public function test_source_weights_scale_and_zero_ignores_a_site(): void
    {
        $snapshots = [
            'hana' => $this->snapshot([]),
            'ahd' => $this->snapshot([9 => 10]),
            'neko-miku' => $this->snapshot([8 => 10]),
        ];

        $this->assertSame([8, 9], $this->ids($snapshots, ['mode' => 'network', 'source_weights' => ['ahd' => 0.5]]));
        $this->assertSame([8], $this->ids($snapshots, ['mode' => 'network', 'source_weights' => ['ahd' => 0]]));
    }

    public function test_a_site_that_stopped_publishing_drops_out(): void
    {
        $snapshots = [
            'hana' => $this->snapshot([]),
            'ahd' => $this->snapshot([9 => 10], age: 3 * 86400),
            'neko-miku' => $this->snapshot([8 => 10]),
        ];

        $this->assertSame([8], $this->ids($snapshots, ['mode' => 'network']));
    }

    public function test_pins_lead_in_order_and_exclusions_vanish(): void
    {
        $snapshots = [
            'hana' => $this->snapshot([1 => 5, 2 => 4, 3 => 3]),
        ];

        $this->assertSame([7, 3, 1], $this->ids($snapshots, ['mode' => 'own', 'pinned' => [7, 3], 'excluded' => [2]]));
    }

    public function test_an_excluded_pin_is_not_shown(): void
    {
        $snapshots = ['hana' => $this->snapshot([1 => 5])];

        $this->assertSame([1], $this->ids($snapshots, ['mode' => 'own', 'pinned' => [7], 'excluded' => [7]]));
    }

    public function test_limit_applies_after_pins(): void
    {
        $snapshots = ['hana' => $this->snapshot([1 => 5, 2 => 4])];

        $this->assertSame([7, 1], $this->ids($snapshots, ['mode' => 'own', 'pinned' => [7]], limit: 2));
    }

    public function test_views_are_own_plus_counted_network_views(): void
    {
        $snapshots = [
            'hana' => $this->snapshot([1 => 5]),
            'ahd' => $this->snapshot([1 => 100]),
        ];

        $this->assertSame(105, TrendingNetwork::blend('hana', $this->settings(), $snapshots, 1, self::NOW)[0]['views']);
        $this->assertSame(5, TrendingNetwork::blend('hana', $this->settings(['mode' => 'own']), $snapshots, 1, self::NOW)[0]['views']);
    }

    public function test_settings_layer_defaults_global_then_site(): void
    {
        $settings = TrendingNetwork::resolveSettings(
            ['mode' => 'network', 'days' => 3, 'network_weight' => 0.5],
            ['days' => 14],
        );

        $this->assertSame('network', $settings['mode']);
        $this->assertSame(14, $settings['days']);
        $this->assertSame(0.5, $settings['network_weight']);
        $this->assertSame(TrendingNetwork::DEFAULTS['refresh_minutes'], $settings['refresh_minutes']);
    }

    public function test_settings_are_clamped_and_cleaned(): void
    {
        $settings = TrendingNetwork::resolveSettings(
            ['mode' => 'bogus', 'network_weight' => 4, 'days' => 0, 'pinned' => ['12', 'x', 12, -1], 'source_weights' => ['ahd' => -2]],
            [],
        );

        $this->assertSame('blend', $settings['mode']);
        $this->assertSame(1.0, $settings['network_weight']);
        $this->assertSame(1, $settings['days']);
        $this->assertSame([12], $settings['pinned']);
        $this->assertSame(['ahd' => 0.0], $settings['source_weights']);
    }
}
