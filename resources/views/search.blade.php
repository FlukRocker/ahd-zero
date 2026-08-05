@extends('layouts.app')

@section('title', 'ค้นหา: ' . $query)
@section('description', 'ผลการค้นหาอนิเมะสำหรับ "' . $query . '" ที่ Anime HD Zero')
@section('robots', 'noindex,follow')

@php
    use App\Support\CardPresenter;
    $items = CardPresenter::collection($animes->items());
@endphp

@section('content')
    <x-content-with-sidebar class="mt-10">
        <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">ผลการค้นหา</div>
        <h1 class="font-display text-[36px] leading-none italic md:text-[46px]">“{{ $query }}”</h1>
        <div class="mt-2 font-mono text-[12px]" style="color: hsl(var(--fg-muted))">พบ {{ $animes->total() }} รายการ</div>

        <div class="mt-10">
            @if (! empty($items))
                <x-card-grid class="!grid-cols-2 sm:!grid-cols-3 lg:!grid-cols-4">
                    @foreach ($items as $item)
                        <x-poster-card :item="$item" />
                    @endforeach
                </x-card-grid>
                {{ $animes->links('pagination.ahd') }}
            @else
                <p class="py-16 text-center" style="color: hsl(var(--fg-muted))">ไม่พบอนิเมะที่ตรงกับคำค้นหา</p>
            @endif
        </div>
    </x-content-with-sidebar>
@endsection
