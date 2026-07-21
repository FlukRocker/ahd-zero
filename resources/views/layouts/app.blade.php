<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ $appearance ?? 'system' }}"
      @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#faf7f0" media="(prefers-color-scheme: light)">
    <meta name="color-scheme" content="light dark">
    <meta name="format-detection" content="telephone=no">

    {{-- Early theme resolver — prevents FOUC. --}}
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('appearance');
                var appearance = saved || '{{ $appearance ?? 'system' }}' || 'system';
                var resolved = appearance;
                if (appearance === 'system') {
                    resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.classList.toggle('dark', resolved === 'dark');
                document.documentElement.setAttribute('data-theme', resolved);

                var cfgRaw = localStorage.getItem('ahd.config');
                if (cfgRaw) {
                    var cfg = JSON.parse(cfgRaw);
                    if (cfg.density) document.documentElement.setAttribute('data-density', cfg.density);
                    if (cfg.typePairing) document.documentElement.setAttribute('data-type', cfg.typePairing === 'fraunces' ? 'alt' : 'default');
                }
            } catch (e) {}
        })();
    </script>

    <style>
        html { background: hsl(40 33% 97%); }
        html.dark, html[data-theme='dark'] { background: hsl(0 0% 4%); }
    </style>

    @include('partials.seo')

    <meta name="application-name" content="{{ config('app.name', 'Anime HD Zero') }}">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Anime HD Zero') }}">

    <link rel="preconnect" href="https://img-cdn-proxy.shirokami.me" crossorigin>
    <link rel="dns-prefetch" href="https://img.shirokami.me">
    <link rel="dns-prefetch" href="https://img-cdn.shirokami.me">
    <link rel="dns-prefetch" href="https://akuma-player.xyz">

    {{-- Critical font preload — hashes change per build; resolved paths cached 1 day. --}}
    @php
        $preloadFonts = \Illuminate\Support\Facades\Cache::remember(
            'font-preload-paths.v1',
            now()->addDay(),
            function () {
                $dir = public_path('build/assets');
                if (!is_dir($dir)) return [];
                $needed = ['instrument-serif-latin-400-normal', 'geist-sans-latin-400-normal'];
                $found = [];
                foreach (glob($dir.'/*.woff2') ?: [] as $path) {
                    $name = basename($path);
                    foreach ($needed as $needle) {
                        if (str_starts_with($name, $needle.'-')) {
                            $found[$needle] = '/build/assets/'.$name;
                            break;
                        }
                    }
                }
                return array_values($found);
            },
        );
    @endphp
    @foreach ($preloadFonts as $fontHref)
        <link rel="preload" href="{{ $fontHref }}" as="font" type="font/woff2" crossorigin>
    @endforeach

    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="shortcut icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" type="image/png" href="/apple-touch-icon.png">

    @if ($gaId = config('services.google_analytics.measurement_id'))
        {{-- GA4 deferred — loads only on real user interaction. --}}
        <script>
            (function () {
                var id = @json($gaId);
                window.dataLayer = window.dataLayer || [];
                function gtag(){ dataLayer.push(arguments); }
                window.gtag = gtag;
                gtag('js', new Date());
                gtag('config', id, { send_page_view: false });

                var loaded = false;
                function load() {
                    if (loaded) return;
                    loaded = true;
                    var s = document.createElement('script');
                    s.async = true;
                    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
                    document.head.appendChild(s);
                    gtag('event', 'page_view', {
                        page_location: location.href,
                        page_path: location.pathname + location.search,
                        page_title: document.title,
                    });
                }

                ['pointerdown', 'touchstart', 'keydown', 'scroll', 'mousemove'].forEach(function (ev) {
                    addEventListener(ev, load, { once: true, passive: true, capture: true });
                });
                setTimeout(load, 30000);
                addEventListener('pagehide', load, { once: true, capture: true });
            })();
        </script>
    @endif

    @include('partials.schema.website')

    @stack('head')

    @vite(['resources/js/blade.js'])
</head>

<body class="min-h-screen bg-[hsl(var(--bg))] font-sans text-[hsl(var(--fg))] antialiased">
    <div
        class="flex min-h-screen flex-col"
        x-data="{ searchOpen: false }"
        @keydown.window.meta.k.prevent="searchOpen = true"
        @keydown.window.ctrl.k.prevent="searchOpen = true"
    >
        <x-ads-navbar />
        <x-site-header />

        <main class="flex-1">
            @yield('content')
        </main>

        <x-site-footer />
        <x-search-overlay />
    </div>

    @stack('scripts')
</body>

</html>
