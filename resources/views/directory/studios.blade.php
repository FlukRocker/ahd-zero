@extends('layouts.app')

@section('title', 'สตูดิโออนิเมะทั้งหมด')
@section('description', 'รายชื่อสตูดิโอผู้ผลิตอนิเมะทั้งหมด ค้นหาและดูผลงานของแต่ละสตูดิโอที่ Anime HD Zero')

@section('content')
    <x-content-with-sidebar class="mt-10">
        <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">ไดเรกทอรี</div>
        <h1 class="font-display text-[40px] leading-none italic md:text-[52px]">สตูดิโอ</h1>
        <x-directory-search action="/studios" :query="$query" placeholder="ค้นหาสตูดิโอ…" />

        <div class="mt-10">
            @if ($studios->count())
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($studios as $s)
                        <a href="/studio/{{ $s->id }}" class="rounded-xl px-4 py-3 transition-colors hover:bg-[hsl(var(--bg-soft))]" style="border: 1px solid hsl(var(--border-ahd));">
                            <div class="font-medium text-[15px]" style="color: hsl(var(--fg))">{{ $s->name }}</div>
                            @if ($s->name_japanese)
                                <div class="font-mono text-[11px]" style="color: hsl(var(--fg-faint))">{{ $s->name_japanese }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>
                {{ $studios->links('pagination.ahd') }}
            @else
                <p class="py-16 text-center" style="color: hsl(var(--fg-muted))">ไม่พบสตูดิโอ</p>
            @endif
        </div>
    </x-content-with-sidebar>
@endsection
