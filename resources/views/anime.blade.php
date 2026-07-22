@extends('layouts.app')

@section('title', $anime['cat_title'])
@section('description', \Illuminate\Support\Str::limit(strip_tags($anime['cat_desc'] ?? ('ดู ' . $anime['cat_title'] . ' ออนไลน์')), 200, ''))
@section('og_type', 'video.tv_show')
@if (! empty($anime['cat_image']))
    @section('og_image', $anime['cat_image'])
@endif

@php
    use App\Support\CardPresenter;
    use App\Support\Img;
    use App\Support\Schema;

    // buildAnimeDetail returns Collections for these relation fields; normalize
    // to plain arrays so empty()/array_slice()/count() behave (an empty
    // Collection object is truthy under empty(), which would render blank
    // sections). Each item inside is already an associative array.
    foreach (['genres', 'studios', 'producers', 'licensors', 'characters', 'staff', 'episode_list', 'series_anime', 'related_anime'] as $relKey) {
        $anime[$relKey] = collect($anime[$relKey] ?? [])->values()->all();
    }

    $firstEp = $anime['episode_list'][0] ?? null;
    $heroImg = $anime['banner_md'] ?: ($anime['banner_original'] ?? null) ?: ($anime['cat_image'] ?? null);
    $heroSrc = $heroImg ? (Img::url($heroImg, ['width' => 1600, 'format' => 'webp']) ?? $heroImg) : null;
    $poster = $anime['cover_md'] ?: ($anime['cat_image'] ?? null);
    $seriesCards = CardPresenter::collection($anime['series_anime'] ?? []);

    $tvSeries = Schema::tvSeries([
        'name' => $anime['cat_title'],
        'alternateName' => $anime['title_japanese'] ?? null,
        'description' => $anime['cat_desc'] ? strip_tags($anime['cat_desc']) : null,
        'image' => $anime['cat_image'] ?? null,
        'url' => '/anime/' . $anime['cat_id'],
        'numberOfEpisodes' => $anime['episodes'] ?? null,
        'startDate' => $anime['aired_from_iso'] ?? null,
        'endDate' => $anime['aired_to_iso'] ?? null,
        'genre' => collect($anime['genres'] ?? [])->pluck('name')->all(),
        'productionCompany' => collect($anime['studios'] ?? [])->map(fn ($s) => ['name' => $s['name']])->all(),
    ]);
@endphp

@if ($heroSrc)
    @push('head')
        <link rel="preload" as="image" href="{{ $heroSrc }}" fetchpriority="high">
    @endpush
@endif

@section('content')
    <x-json-ld :data="$tvSeries" />
    <x-json-ld :data="\App\Support\Schema::breadcrumb([
        ['name' => 'หน้าแรก', 'url' => '/'],
        ['name' => $anime['cat_title'], 'url' => '/anime/' . $anime['cat_id']],
    ])" />

    <section class="relative overflow-hidden">
        @if ($heroSrc)
            <div class="absolute inset-0">
                {{-- Full-opacity image so it stays LCP-eligible (Lighthouse skips
                     opacity<1 images); the faded look comes from the scrim below.
                     This makes the fast (Bunny-optimized, preloaded) hero the LCP
                     instead of a heavier ad banner. --}}
                <img src="{{ $heroSrc }}" class="h-full w-full object-cover" alt="{{ $anime['cat_title'] }}" fetchpriority="high" decoding="async">
                <div class="absolute inset-0" style="background: hsl(var(--bg) / 0.45)"></div>
                <div class="grad-hero"></div>
            </div>
        @endif
        <div class="relative mx-auto grid max-w-[1440px] grid-cols-12 gap-8 px-6 pt-28 pb-16 lg:px-10">
            <div class="col-span-12 md:col-span-4 lg:col-span-3">
                <div class="poster-card" style="box-shadow: 0 40px 80px -30px rgba(0,0,0,0.5)">
                    @if ($poster)
                        <img src="{{ Img::url($poster, ['width' => 480, 'format' => 'webp']) ?? $poster }}" alt="{{ $anime['cat_title'] }}" width="300" height="450" decoding="async">
                    @endif
                </div>
            </div>
            <div class="col-span-12 md:col-span-8 lg:col-span-9">
                @php $metaTop = array_filter([$anime['anime_type'] ?? null, $anime['premiered_season'] ?? null, $anime['premiered_year'] ?? null]); @endphp
                @if ($metaTop)
                    <div class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase" style="color: hsl(var(--fg-muted))">{{ implode(' · ', $metaTop) }}</div>
                @endif
                <h1 class="font-display mb-4 leading-[0.95] italic" style="font-size: clamp(42px, 6vw, 72px); text-wrap: balance;">{{ $anime['cat_title'] }}</h1>
                @if (! empty($anime['title_japanese']))
                    <div class="font-display mb-6 text-[26px] italic" style="color: hsl(var(--accent))">{{ $anime['title_japanese'] }}</div>
                @endif

                <div class="mb-6 flex flex-wrap items-center gap-3">
                    @if (! empty($anime['anime_status']))<span class="chip chip-accent font-mono">{{ $anime['anime_status'] }}</span>@endif
                    @if (! empty($anime['episodes']))<span class="chip font-mono">{{ $anime['episodes'] }} EP</span>@endif
                    @if (! empty($anime['duration']))<span class="chip font-mono">{{ $anime['duration'] }}</span>@endif
                    @if (! empty($anime['rating']))<span class="chip font-mono">{{ $anime['rating'] }}</span>@endif
                </div>

                @if (! empty($anime['cat_desc']))
                    {{-- cat_desc is trusted HTML from the admin/import pipeline. --}}
                    <div class="anime-desc mb-6 max-w-3xl text-[15px]" style="color: hsl(var(--fg-muted))">{!! $anime['cat_desc'] !!}</div>
                @endif

                <div class="mb-8 flex flex-wrap items-center gap-3">
                    @if ($firstEp)
                        <a href="/anime/{{ $anime['cat_id'] }}/episode/{{ $firstEp['list_id'] }}" class="btn btn-primary">▶ ดูตอนนี้</a>
                    @endif
                    <button class="btn btn-ghost" type="button">+ เพิ่มในรายการ</button>
                </div>

                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-[13px] md:grid-cols-3" style="color: hsl(var(--fg-muted))">
                    @if (! empty($anime['aired_from']))
                        <div><dt class="font-mono text-[10px] tracking-widest uppercase opacity-70">ออกอากาศ</dt><dd>{{ $anime['aired_from'] }}@if (! empty($anime['aired_to'])) — {{ $anime['aired_to'] }}@endif</dd></div>
                    @endif
                    @if (! empty($anime['broadcast']))
                        <div><dt class="font-mono text-[10px] tracking-widest uppercase opacity-70">ตารางออกอากาศ</dt><dd>{{ $anime['broadcast'] }}</dd></div>
                    @endif
                    @if (! empty($anime['source']))
                        <div><dt class="font-mono text-[10px] tracking-widest uppercase opacity-70">ต้นฉบับ</dt><dd>{{ $anime['source'] }}</dd></div>
                    @endif
                    @if (! empty($anime['studios']))
                        <div><dt class="font-mono text-[10px] tracking-widest uppercase opacity-70">สตูดิโอ</dt><dd>@foreach ($anime['studios'] as $i => $s)<a href="/studio/{{ $s['id'] }}" class="u-grow">{{ $s['name'] }}</a>@if ($i < count($anime['studios']) - 1), @endif @endforeach</dd></div>
                    @endif
                    @if (! empty($anime['genres']))
                        <div class="col-span-2 md:col-span-3"><dt class="font-mono text-[10px] tracking-widest uppercase opacity-70">หมวดหมู่</dt><dd class="mt-1 flex flex-wrap gap-2">@foreach ($anime['genres'] as $g)<span class="chip font-mono">{{ $g['name'] }}</span>@endforeach</dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </section>

    {{-- Ads below the hero so the preloaded hero image is the LCP; the ad
         creatives (many, heavy, third-party) then load lazily below the fold
         and can't dominate LCP. --}}
    @if (! empty($adsBanners))
        <section class="mx-auto mt-8 max-w-[1440px] px-6 lg:px-10">
            <x-ads-banner :banners="$adsBanners" />
        </section>
    @endif

    @if (! empty($anime['episode_list']))
        <section class="mx-auto mt-10 max-w-[1440px] px-6 lg:px-10">
            <x-section-header eyebrow="สตรีม" :title="'ตอนทั้งหมด (' . count($anime['episode_list']) . ')'" />
            <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                @foreach ($anime['episode_list'] as $ep)
                    <a href="/anime/{{ $anime['cat_id'] }}/episode/{{ $ep['list_id'] }}" class="ep-row flex items-center gap-3 rounded-lg p-3" style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));">
                        <span aria-hidden="true">▶</span>
                        <span class="text-[13px]">{{ $ep['list_title'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if (! empty($anime['characters']))
        <section class="mx-auto mt-20 max-w-[1440px] px-6 lg:px-10">
            <x-section-header eyebrow="นักแสดง" title="ตัวละครและนักพากย์" />
            <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));">
                @foreach (array_slice($anime['characters'], 0, 12) as $c)
                    <div class="flex items-center gap-3 rounded-xl p-3" style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));">
                        @if (! empty($c['image_url']))
                            <img src="{{ $c['image_url'] }}" alt="{{ $c['name'] }}" class="h-12 w-12 shrink-0 rounded-full object-cover" width="48" height="48" loading="lazy" decoding="async">
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ $c['name'] }}</div>
                            @if (! empty($c['voice_actor']))
                                <div class="truncate text-[12px]" style="color: hsl(var(--fg-muted))">พากย์โดย <a href="/voice-actor/{{ $c['voice_actor']['id'] }}" class="u-grow">{{ $c['voice_actor']['name'] }}</a></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if (! empty($seriesCards))
        <section class="mx-auto mt-20 max-w-[1440px] px-6 lg:px-10">
            <x-section-header eyebrow="ซีรีส์" title="ภาคอื่นในซีรีส์" />
            <x-rail :items="$seriesCards" />
        </section>
    @endif

    {{-- Comment section: mount point only. The Alpine comment component lands in Phase 3. --}}
    <section class="mx-auto mt-20 mb-20 max-w-[1440px] px-6 lg:px-10">
        <div id="comments" data-commentable-type="anime" data-commentable-id="{{ $anime['cat_id'] }}"></div>
    </section>

    <x-ads-floating :payload="$floatingAds ?? null" />
@endsection
