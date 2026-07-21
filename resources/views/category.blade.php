@extends('layouts.app')

@section('title', $categoryName . ' — ดูอนิเมะออนไลน์ HD')
@section('description', 'ดูอนิเมะ ' . $categoryName . ' ออนไลน์ฟรี คุณภาพ HD ครบทุกเรื่อง อัปเดตทุกวัน รับชมได้ทุกอุปกรณ์ผ่าน Anime HD Zero')

@php
    use App\Support\CardPresenter;
    $items = CardPresenter::collection($anime->items());
@endphp

@section('content')
    <x-json-ld :data="\App\Support\Schema::breadcrumb([
        ['name' => 'หน้าแรก', 'url' => '/'],
        ['name' => $categoryName, 'url' => '/category/' . $currentType],
    ])" />

    <section class="mx-auto mt-10 max-w-[1440px] px-6 lg:px-10">
        <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">หมวดหมู่</div>
        <h1 class="font-display text-[40px] leading-none italic md:text-[52px]">{{ $categoryName }}</h1>

        <div class="mt-10">
            @if (! empty($items))
                <x-card-grid>
                    @foreach ($items as $item)
                        <x-poster-card :item="$item" />
                    @endforeach
                </x-card-grid>
                {{ $anime->links('pagination.ahd') }}
            @else
                <p class="py-16 text-center" style="color: hsl(var(--fg-muted))">ยังไม่มีอนิเมะในหมวดหมู่นี้</p>
            @endif
        </div>
    </section>

    <x-about-seo />
@endsection
