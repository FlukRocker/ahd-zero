@php
    $ldWebsite = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => config('app.name'),
        'url' => config('app.url'),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => rtrim(config('app.url'), '/') . '/search/results?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
    $ldOrg = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => config('app.name'),
        'url' => config('app.url'),
        'logo' => rtrim(config('app.url'), '/') . '/favicon.png',
    ];
@endphp
<script type="application/ld+json">{!! json_encode($ldWebsite, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($ldOrg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
