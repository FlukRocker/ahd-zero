<?php

namespace App\Support;

class CardPresenter
{
    private const PLACEHOLDER = '/images/placeholder-poster.webp';

    /**
     * Normalize an Anime model or array into the card shape used by every
     * poster/landscape component. Ports resources/js/lib/animeCard.ts.
     *
     * @param  \Illuminate\Database\Eloquent\Model|array<string,mixed>  $item
     * @return array<string,mixed>
     */
    public static function make($item): array
    {
        $get = static fn (string $key, $default = null) => data_get($item, $key, $default);

        $catType = (int) ($get('cat_type') ?? 1);

        return [
            'id' => $get('cat_id'),
            'title' => (string) $get('cat_title'),
            'poster' => self::resolvePoster($get),
            'landscape' => self::resolveLandscape($get),
            'tag' => self::resolveTag($get, $catType),
            'ep' => self::resolveEp($get, $catType),
            'kanji' => (string) ($get('title_japanese') ?? ''),
            'genre' => (string) ($get('anime_type') ?? ''),
            'href' => '/anime/'.$get('cat_id'),
            'cat_type' => $catType,
        ];
    }

    /**
     * @param  iterable<mixed>  $items
     * @return list<array<string,mixed>>
     */
    public static function collection(iterable $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = self::make($item);
        }

        return $out;
    }

    private static function resolvePoster(callable $get): string
    {
        return $get('cover_md') ?: $get('cat_image') ?: $get('cover_th') ?: self::PLACEHOLDER;
    }

    private static function resolveLandscape(callable $get): string
    {
        return $get('banner_md') ?: $get('banner_original') ?: $get('cat_image') ?: self::PLACEHOLDER;
    }

    private static function resolveTag(callable $get, int $catType): ?string
    {
        $status = $get('anime_status');
        if ($status === 'Currently Airing' || $status === 'airing') {
            return 'กำลังฉาย';
        }

        return match ($catType) {
            1 => 'ซับไทย',
            2 => 'พากย์ไทย',
            3 => 'มูฟวี่',
            default => null,
        };
    }

    private static function resolveEp(callable $get, int $catType): string
    {
        if ($catType === 3) {
            return 'มูฟวี่';
        }

        $count = $get('episode_list_count');
        if ($count === null) {
            $count = $get('episodes');
        }

        return $count === null ? '' : $count.' ตอน';
    }
}
