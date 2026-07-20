@extends('layouts.app')

@section('title', ($code ?? 'Error') . ' — ' . config('app.name'))
@section('robots', 'noindex,follow')

@section('content')
    <section class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center"
             x-data x-init="$reveal($el)">
        <p class="text-6xl font-semibold text-[hsl(var(--accent))]">{{ $code ?? 'Error' }}</p>
        <h1 class="mt-4 text-2xl font-semibold text-[hsl(var(--fg))]">{{ $title ?? 'Something went wrong' }}</h1>
        <p class="mt-2 text-[hsl(var(--fg-muted))]">{{ $message ?? '' }}</p>
        <a href="{{ url('/') }}"
           class="mt-8 rounded-lg bg-[hsl(var(--accent))] px-5 py-2.5 font-medium text-[hsl(var(--accent-fg))]">
            กลับหน้าแรก
        </a>
    </section>
@endsection
