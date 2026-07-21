<?php

namespace App\Support;

class Schema
{
    /**
     * @param  array{name:string,alternateName?:?string,description?:?string,image?:?string,url:string,numberOfEpisodes?:?int,startDate?:?string,endDate?:?string,genre?:array<int,string>,actor?:array<int,array{name:string}>,productionCompany?:array<int,array{name:string}>}  $input
     * @return array<string,mixed>
     */
    public static function tvSeries(array $input): array
    {
        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'TVSeries',
            'name' => $input['name'],
            'url' => self::absolute($input['url']),
        ];

        foreach (['alternateName', 'description', 'image', 'startDate', 'endDate'] as $key) {
            if (! empty($input[$key])) {
                $payload[$key] = $input[$key];
            }
        }
        if (isset($input['numberOfEpisodes']) && $input['numberOfEpisodes'] !== null) {
            $payload['numberOfEpisodes'] = $input['numberOfEpisodes'];
        }
        if (! empty($input['genre'])) {
            $payload['genre'] = array_values($input['genre']);
        }
        if (! empty($input['actor'])) {
            $payload['actor'] = array_map(
                fn (array $a) => ['@type' => 'Person', 'name' => $a['name']],
                $input['actor'],
            );
        }
        if (! empty($input['productionCompany'])) {
            $payload['productionCompany'] = array_map(
                fn (array $c) => ['@type' => 'Organization', 'name' => $c['name']],
                $input['productionCompany'],
            );
        }

        return $payload;
    }

    /**
     * @param  array{name:string,description?:?string,thumbnailUrl?:?string,uploadDate?:?string,contentUrl?:?string,embedUrl?:?string,partOfSeries?:array{name:string,url:string}}  $input
     * @return array<string,mixed>
     */
    public static function videoObject(array $input): array
    {
        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $input['name'],
        ];

        foreach (['description', 'thumbnailUrl', 'uploadDate', 'contentUrl', 'embedUrl'] as $key) {
            if (! empty($input[$key])) {
                $payload[$key] = $input[$key];
            }
        }
        if (! empty($input['partOfSeries'])) {
            $payload['partOfSeries'] = [
                '@type' => 'TVSeries',
                'name' => $input['partOfSeries']['name'],
                'url' => self::absolute($input['partOfSeries']['url']),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<int,array{name:string,url:string}>  $crumbs
     * @return array<string,mixed>
     */
    public static function breadcrumb(array $crumbs): array
    {
        $items = [];
        foreach (array_values($crumbs) as $i => $c) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => self::absolute($c['url']),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    private static function absolute(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $base = rtrim((string) config('app.url'), '/');
        $trimmed = ltrim($path, '/');

        return $trimmed === '' ? $base : $base . '/' . $trimmed;
    }
}
