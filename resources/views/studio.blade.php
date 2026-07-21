@extends('layouts.app')

@section('title', $studio['name'] . ' — สตูดิโออนิเมะ')
@section('description', 'รวมผลงานอนิเมะจากสตูดิโอ ' . $studio['name'] . ' ดูออนไลน์ฟรี HD ที่ Anime HD Zero')

@php
    use App\Support\CardPresenter;
    $items = CardPresenter::collection($anime->items());
@endphp

@section('content')
    <x-json-ld :data="\App\Support\Schema::breadcrumb([
        ['name' => 'หน้าแรก', 'url' => '/'],
        ['name' => 'สตูดิโอ', 'url' => '/studios'],
        ['name' => $studio['name'], 'url' => '/studio/' . $studio['id']],
    ])" />

    <section class="mx-auto mt-10 max-w-[1440px] px-6 lg:px-10">
        <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">สตูดิโอ</div>
        <h1 class="font-display text-[40px] leading-none italic md:text-[52px]">{{ $studio['name'] }}</h1>
        @if (! empty($studio['name_japanese']))
            <div class="mt-2 font-mono text-[13px]" style="color: hsl(var(--fg-muted))">{{ $studio['name_japanese'] }}</div>
        @endif

        <div class="mt-10">
            @if (! empty($items))
                <x-card-grid>
                    @foreach ($items as $item)
                        <x-poster-card :item="$item" />
                    @endforeach
                </x-card-grid>
                {{ $anime->links('pagination.ahd') }}
            @else
                <p class="py-16 text-center" style="color: hsl(var(--fg-muted))">ยังไม่มีผลงานอนิเมะจากสตูดิโอนี้</p>
            @endif
        </div>
    </section>
@endsection
