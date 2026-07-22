@extends('layouts.app')

@php
    use App\Support\CardPresenter;
    use App\Support\Schema;

    $pageTitle = $anime['cat_title'] . ' — ' . $currentEpisode['list_title'];

    $eps = collect($episodes)->values()->all();
    $curIdx = collect($eps)->search(fn ($e) => $e['list_id'] == $currentEpisode['list_id']);
    $prev = ($curIdx !== false && $curIdx > 0) ? $eps[$curIdx - 1] : null;
    $next = ($curIdx !== false && $curIdx < count($eps) - 1) ? $eps[$curIdx + 1] : null;

    $relatedCards = CardPresenter::collection($relatedAnime ?? []);

    // Server-compute both player URLs (no client base64 needed). Force https so
    // a misconfigured http env can't trigger a mixed-content block.
    $playerUrl = $currentEpisode['player_url'] ?? null;
    $adsEmbed = preg_replace('#^http://#i', 'https://', $playerConfig['adsEmbedUrl'] ?? 'https://anime-hdzero.com/player/embed.php');
    $srcAds = $playerUrl ? $adsEmbed . '?link=' . urlencode(base64_encode($playerUrl)) : null;
    $srcDirect = $playerUrl ? preg_replace('#^http://#i', 'https://', $playerUrl) : null;

    $videoObject = Schema::videoObject([
        'name' => $pageTitle,
        'description' => $anime['cat_desc'] ? strip_tags($anime['cat_desc']) : null,
        'thumbnailUrl' => $anime['cat_image'] ?? null,
        'uploadDate' => $currentEpisode['upload_date_iso'] ?? null,
        'embedUrl' => $playerUrl,
        'partOfSeries' => ['name' => $anime['cat_title'], 'url' => '/anime/' . $anime['cat_id']],
    ]);
@endphp

@section('title', $pageTitle)
@section('description', 'ดู ' . $anime['cat_title'] . ' — ' . $currentEpisode['list_title'] . ' ออนไลน์ฟรี HD ที่ Anime HD Zero')
@section('og_type', 'video.episode')
@if (! empty($anime['cat_image']))
    @section('og_image', $anime['cat_image'])
@endif

@section('content')
    <x-json-ld :data="$videoObject" />
    <x-json-ld :data="\App\Support\Schema::breadcrumb([
        ['name' => 'หน้าแรก', 'url' => '/'],
        ['name' => $anime['cat_title'], 'url' => '/anime/' . $anime['cat_id']],
        ['name' => $currentEpisode['list_title'], 'url' => '/anime/' . $anime['cat_id'] . '/episode/' . $currentEpisode['list_id']],
    ])" />

    {{-- eager-first: the episode page has no hero image, so the first ad is the
         largest above-the-fold element (the LCP). Load it eagerly instead of
         deferring it to ~20s. --}}
    @if (! empty($adsBanners))
        <section class="mx-auto mt-6 max-w-[1440px] px-6 lg:px-10">
            <x-ads-banner :banners="$adsBanners" eager-first />
        </section>
    @endif

    <section class="mx-auto max-w-[1440px] px-6 pt-10 pb-16 lg:px-10">
        <div class="mb-6">
            <a href="/anime/{{ $anime['cat_id'] }}" class="u-grow inline-flex items-center gap-2 font-mono text-[13px] tracking-widest uppercase" style="color: hsl(var(--fg-muted))">‹ {{ $anime['cat_title'] }}</a>
            <h1 class="font-display mt-3 leading-tight italic" style="font-size: clamp(32px, 4vw, 52px)">{{ $currentEpisode['list_title'] }}</h1>
        </div>

        <div class="grid grid-cols-12 gap-6" x-data="{ mode: (localStorage.getItem('ahd.playerMode') === 'direct' ? 'direct' : 'ads'), set(m) { this.mode = m; try { localStorage.setItem('ahd.playerMode', m); } catch (e) {} } }">
            <div class="col-span-12 lg:col-span-8">
                @if (! empty($playerAds['top']))
                    <x-player-ad-slot :ad="$playerAds['top']" class="mb-3" />
                @endif

                <div class="relative w-full overflow-hidden rounded-2xl" style="aspect-ratio: 16/9; background: #000">
                    @if ($srcAds)
                        <iframe
                            :src="mode === 'ads' ? @js($srcAds) : @js($srcDirect)"
                            referrerpolicy="strict-origin-when-cross-origin"
                            class="absolute inset-0 h-full w-full"
                            scrolling="no"
                            frameborder="0"
                            allowfullscreen
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture;"
                        ></iframe>
                    @else
                        <div class="absolute inset-0 flex items-center justify-center text-white/60">ไม่สามารถเล่นได้</div>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    @if ($prev)
                        <a href="/anime/{{ $anime['cat_id'] }}/episode/{{ $prev['list_id'] }}" class="btn btn-ghost">‹ ก่อนหน้า</a>
                    @endif
                    @if ($next)
                        <a href="/anime/{{ $anime['cat_id'] }}/episode/{{ $next['list_id'] }}" class="btn btn-primary">ถัดไป ›</a>
                    @endif

                    <div class="seg ml-auto">
                        <button type="button" :class="mode === 'ads' ? 'on' : ''" @click="set('ads')">ตัวเล่นหลัก</button>
                        <button type="button" :class="mode === 'direct' ? 'on' : ''" @click="set('direct')">ตัวเล่นสำรอง</button>
                    </div>
                </div>

                @if (! empty($playerAds['bottom']))
                    <x-player-ad-slot :ad="$playerAds['bottom']" class="mt-4" />
                @endif

                @if (! empty($anime['cat_desc']))
                    <div class="mt-8">
                        <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">เรื่องย่อ</div>
                        {{-- cat_desc is trusted HTML from the admin/import pipeline. --}}
                        <div class="anime-desc max-w-3xl text-[14px]" style="color: hsl(var(--fg-muted))">{!! $anime['cat_desc'] !!}</div>
                    </div>
                @endif
            </div>

            <aside class="col-span-12 lg:col-span-4">
                <div class="rounded-2xl p-4" style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd)); max-height: 640px; overflow: auto;">
                    <div class="mb-3 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">รายการตอน</div>
                    <ul class="space-y-1">
                        @foreach ($eps as $ep)
                            <a href="/anime/{{ $anime['cat_id'] }}/episode/{{ $ep['list_id'] }}" class="ep-row flex items-center gap-3 rounded-lg p-2 text-[13px]" style="{{ $ep['list_id'] == $currentEpisode['list_id'] ? 'background: hsl(var(--accent) / 0.15); color: hsl(var(--fg));' : '' }}">
                                <span aria-hidden="true">▶</span>
                                <span class="truncate">{{ $ep['list_title'] }}</span>
                            </a>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>
    </section>

    @if (! empty($relatedCards))
        <section class="mx-auto mt-8 max-w-[1440px] px-6 lg:px-10">
            <x-section-header eyebrow="คุณอาจชอบ" title="อนิเมะที่เกี่ยวข้อง" />
            <x-rail :items="$relatedCards" />
        </section>
    @endif

    {{-- Comment section: mount point only. The Alpine comment component lands in Phase 3. --}}
    <section class="mx-auto mt-12 mb-20 max-w-[1440px] px-6 lg:px-10">
        <div id="comments" data-commentable-type="episode" data-commentable-id="{{ $currentEpisode['list_id'] }}"></div>
    </section>

    <x-ads-floating :payload="$floatingAds ?? null" />
@endsection
