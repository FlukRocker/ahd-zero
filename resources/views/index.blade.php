@extends('layouts.app')

@section('title', 'ดูอนิเมะออนไลน์ ซับไทย พากย์ไทย เดอะมูฟวี่ HD')
@section('description', 'ดูอนิเมะออนไลน์ฟรี รวมอนิเมะใหม่ล่าสุด ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD ดูง่าย ลื่นไหล อัปเดตทุกวัน รับชมได้ทุกอุปกรณ์ผ่าน Anime HD Zero')

@php
    use App\Support\CardPresenter;

    // Hero source mirrors Index.vue: >=3 recommended → recommended, else latest.
    $heroSource = (! empty($recommended) && count($recommended) >= 3) ? $recommended : $anime->items();
    $heroItems = CardPresenter::collection(array_slice($heroSource, 0, 5));
    $latestItems = CardPresenter::collection($anime->items());

    // Rail is hidden below 6 cards — a half-empty rail reads as broken.
    $trendingItems = count($trending) >= 6 ? CardPresenter::collection($trending) : [];
@endphp

@if (! empty($heroItems))
    @push('head')
        @php $hero0 = $heroItems[0]; $heroPreload = \App\Support\Img::url($hero0['landscape'], ['width' => 1600, 'format' => 'webp']) ?? $hero0['landscape']; @endphp
        <link rel="preload" as="image" href="{{ $heroPreload }}" fetchpriority="high">
    @endpush
@endif

@section('content')
    {{-- SEO-hidden keyword-rich H1 (visually hidden, readable by crawlers). --}}
    <h1 style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;">ดูอนิเมะออนไลน์ ซับไทย พากย์ไทย เดอะมูฟวี่ HD - Anime HD Zero</h1>

    @if (! empty($heroItems))
        <x-hero :item="$heroItems[0]" eager />
    @endif

    @if (! empty($trendingItems))
        <section class="mx-auto mt-20 max-w-[1440px] px-6 lg:px-10">
            <x-section-header eyebrow="จากยอดวิว 7 วันล่าสุด" title="มาแรงตอนนี้" />
            <x-rail :items="$trendingItems" />
        </section>
    @endif

    <x-content-with-sidebar class="mt-24">
        <x-section-header eyebrow="อัปเดตล่าสุด" title="ตอนใหม่ล่าสุด" />
        <x-card-grid class="!grid-cols-2 sm:!grid-cols-3 lg:!grid-cols-4">
            @foreach ($latestItems as $item)
                <x-poster-card :item="$item" />
            @endforeach
        </x-card-grid>
        {{ $anime->links('pagination.ahd') }}
    </x-content-with-sidebar>

    <x-about-seo />
@endsection
