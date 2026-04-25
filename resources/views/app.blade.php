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

    {{-- Early theme resolver — prevents FOUC. Reads the user's saved preference and resolves system scheme. --}}
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

    <title inertia>{{ config('app.name', 'Anime HD Zero') }}</title>

    {{-- Fallback description so the page is never indexed without one if the
         per-page useSeo() prop somehow skips a write. Inertia head will
         override this on hydration. --}}
    <meta name="description" content="ดูอนิเมะออนไลน์ฟรี ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD อัปเดตทุกวัน รับชมได้ทุกอุปกรณ์ผ่าน Anime HD Zero">

    <meta name="application-name" content="{{ config('app.name', 'Anime HD Zero') }}">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Anime HD Zero') }}">

    {{-- DNS + TCP early so card poster requests don't pay full handshake cost. --}}
    <link rel="preconnect" href="https://img.shirokami.me" crossorigin>
    <link rel="preconnect" href="https://img-cdn.shirokami.me" crossorigin>
    <link rel="dns-prefetch" href="https://akuma-player.xyz">

    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="shortcut icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" type="image/png" href="/apple-touch-icon.png">

    @routes
    @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
