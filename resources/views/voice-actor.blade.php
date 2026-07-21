@extends('layouts.app')

@section('title', $voiceActor['name'] . ' — นักพากย์อนิเมะ')
@section('description', 'รวมผลงานการพากย์ของ ' . $voiceActor['name'] . ' ดูอนิเมะออนไลน์ฟรี HD ที่ Anime HD Zero')
@if (! empty($voiceActor['image_url']))
    @section('og_image', $voiceActor['image_url'])
@endif

@php
    use App\Support\CardPresenter;
    $items = CardPresenter::collection($anime->items());
@endphp

@section('content')
    <x-json-ld :data="\App\Support\Schema::breadcrumb([
        ['name' => 'หน้าแรก', 'url' => '/'],
        ['name' => 'นักพากย์', 'url' => '/voice-actors'],
        ['name' => $voiceActor['name'], 'url' => '/voice-actor/' . $voiceActor['id']],
    ])" />

    <section class="mx-auto mt-10 max-w-[1440px] px-6 lg:px-10">
        <div class="flex items-center gap-5">
            @if (! empty($voiceActor['image_url']))
                <img src="{{ $voiceActor['image_url'] }}" alt="{{ $voiceActor['name'] }}" loading="eager" decoding="async" width="88" height="88" class="h-22 w-22 shrink-0 rounded-full object-cover" style="height:88px;width:88px;">
            @endif
            <div>
                <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">นักพากย์@if (! empty($voiceActor['language'])) · {{ $voiceActor['language'] }}@endif</div>
                <h1 class="font-display text-[40px] leading-none italic md:text-[52px]">{{ $voiceActor['name'] }}</h1>
                @if (! empty($voiceActor['name_japanese']))
                    <div class="mt-2 font-mono text-[13px]" style="color: hsl(var(--fg-muted))">{{ $voiceActor['name_japanese'] }}</div>
                @endif
            </div>
        </div>

        <div class="mt-10">
            @if (! empty($items))
                <x-card-grid>
                    @foreach ($items as $item)
                        <x-poster-card :item="$item" />
                    @endforeach
                </x-card-grid>
                {{ $anime->links('pagination.ahd') }}
            @else
                <p class="py-16 text-center" style="color: hsl(var(--fg-muted))">ยังไม่มีผลงานการพากย์</p>
            @endif
        </div>
    </section>
@endsection
