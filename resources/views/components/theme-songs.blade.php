{{--
    Opening/ending theme songs with their players.
    Takes the raw column values; App\Support\ThemeSongs validates every id and
    builds every URL (contract: ahd-admin docs/superpowers/specs/2026-10-20-theme-song-players-design.md).
--}}
@props(['opening' => [], 'ending' => []])
@php
    $lists = array_filter([
        'เพลงเปิด' => \App\Support\ThemeSongs::forDisplay($opening),
        'เพลงปิด' => \App\Support\ThemeSongs::forDisplay($ending),
    ]);
    $hasYoutube = collect($lists)->flatten(1)->contains(fn (array $song): bool => $song['youtube'] !== null);
@endphp
@if ($lists !== [])
    <section class="mt-20" style="--theme-player-accent: hsl(var(--accent-strong)); --theme-player-accent-fg: hsl(var(--accent-fg)); --theme-player-bg: hsl(var(--bg-elev)); --theme-player-fg: hsl(var(--fg));">
        <x-section-header eyebrow="เพลงประกอบ" title="เพลงเปิดและเพลงปิด" />
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($lists as $label => $songs)
                <div>
                    <h3 class="mb-3 text-sm font-semibold" style="color: hsl(var(--fg-muted));">{{ $label }}</h3>
                    <ol class="flex flex-col gap-4">
                        @foreach ($songs as $song)
                            <li class="flex flex-col gap-2">
                                <p class="text-sm">{{ $song['name'] }}</p>
                                @if ($song['spotify'])
                                    <iframe src="{{ $song['spotify']['src'] }}" height="{{ $song['spotify']['height'] }}" width="100%" loading="lazy" allow="encrypted-media" title="Spotify: {{ $song['name'] }}" style="border: 0; border-radius: 12px;"></iframe>
                                @endif
                                @if ($song['youtube'])
                                    <theme-yt-player video-id="{{ $song['youtube'] }}" song="{{ $song['name'] }}"></theme-yt-player>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>
        @if ($hasYoutube)
            @vite('resources/js/theme-yt-player.js')
        @endif
    </section>
@endif
