@extends('layouts.app')

@section('title', 'รายการที่บันทึกไว้')
@section('description', 'อนิเมะที่คุณบันทึกไว้บน Anime HD Zero')
@section('robots', 'noindex,nofollow')

@php
    use App\Support\CardPresenter;

    $cards = CardPresenter::collection($items);
@endphp

@section('content')
    <section class="mx-auto mt-16 max-w-[1440px] px-6 lg:px-10">
        <x-section-header eyebrow="ของฉัน" title="รายการที่บันทึกไว้" />

        @if (empty($cards))
            <p class="mt-8 text-[15px]" style="color: hsl(var(--fg-muted))">
                ยังไม่มีรายการที่บันทึกไว้ — กด “+ เพิ่มในรายการ” ที่หน้าอนิเมะเพื่อเก็บไว้ดูภายหลัง
            </p>
        @else
            <x-card-grid>
                @foreach ($cards as $item)
                    <x-poster-card :item="$item" />
                @endforeach
            </x-card-grid>
            {{ $bookmarks->links('pagination.ahd') }}
        @endif
    </section>
@endsection
