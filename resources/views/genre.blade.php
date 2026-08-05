@extends('layouts.app')

@section('title', 'อนิเมะ' . $genreName . ' — ดูออนไลน์ HD')
@section('description', 'ดูอนิเมะแนว ' . $genreName . ' ออนไลน์ฟรี คุณภาพ HD ครบทุกเรื่อง อัปเดตทุกวัน รับชมได้ทุกอุปกรณ์ผ่าน Anime HD Zero')

@php
    use App\Support\CardPresenter;
    $items = CardPresenter::collection($anime->items());
@endphp

@section('content')
    <x-json-ld :data="\App\Support\Schema::breadcrumb([
        ['name' => 'หน้าแรก', 'url' => '/'],
        ['name' => $genreName, 'url' => '/genre/' . $genreSlug],
    ])" />

    <x-content-with-sidebar class="mt-10">
        <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">หมวดหมู่</div>
        <h1 class="font-display text-[40px] leading-none italic md:text-[52px]">{{ $genreName }}</h1>

        <div class="mt-10">
            @if (! empty($items))
                <x-card-grid class="!grid-cols-2 sm:!grid-cols-3 lg:!grid-cols-4">
                    @foreach ($items as $item)
                        <x-poster-card :item="$item" />
                    @endforeach
                </x-card-grid>
                {{ $anime->links('pagination.ahd') }}
            @else
                <p class="py-16 text-center" style="color: hsl(var(--fg-muted))">ยังไม่มีอนิเมะในหมวดหมู่นี้</p>
            @endif
        </div>
    </x-content-with-sidebar>

    <x-about-seo />
@endsection
