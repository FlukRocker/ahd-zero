@extends('layouts.app')

@section('title', 'ทีมงานอนิเมะทั้งหมด')
@section('description', 'รายชื่อทีมงานผู้สร้างอนิเมะทั้งหมด ค้นหาและดูผลงานของแต่ละคนที่ Anime HD Zero')

@section('content')
    <section class="mx-auto mt-10 max-w-[1440px] px-6 lg:px-10">
        <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">ไดเรกทอรี</div>
        <h1 class="font-display text-[40px] leading-none italic md:text-[52px]">ทีมงาน</h1>
        <x-directory-search action="/staff" :query="$query" placeholder="ค้นหาทีมงาน…" />

        <div class="mt-10">
            @if ($staffList->count())
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($staffList as $st)
                        <div class="flex items-center gap-3 rounded-xl px-4 py-3" style="border: 1px solid hsl(var(--border-ahd));">
                            @if ($st->image_url)
                                <img src="{{ $st->image_url }}" alt="{{ $st->name }}" loading="lazy" decoding="async" width="44" height="44" class="shrink-0 rounded-full object-cover" style="height:44px;width:44px;">
                            @else
                                <div class="flex shrink-0 items-center justify-center rounded-full font-display italic" style="height:44px;width:44px;background:hsl(var(--bg-soft));color:hsl(var(--fg-muted))">{{ mb_substr($st->name, 0, 1) }}</div>
                            @endif
                            <div class="min-w-0">
                                <div class="truncate font-medium text-[14px]" style="color: hsl(var(--fg))">{{ $st->name }}</div>
                                @if ($st->name_japanese)
                                    <div class="truncate font-mono text-[11px]" style="color: hsl(var(--fg-faint))">{{ $st->name_japanese }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                {{ $staffList->links('pagination.ahd') }}
            @else
                <p class="py-16 text-center" style="color: hsl(var(--fg-muted))">ไม่พบทีมงาน</p>
            @endif
        </div>
    </section>
@endsection
