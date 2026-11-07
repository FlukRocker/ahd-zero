@extends('layouts.app')

@section('content')
    <main class="mx-auto w-full max-w-3xl px-4 py-6">
        <h1 class="text-xl font-semibold">Chat store</h1>
        <p class="text-sm opacity-70">Balance: {{ $balance }} coins</p>

        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($items as $item)
                <li class="rounded-lg border border-white/10 p-3">
                    <span class="font-medium">{{ $item['name'] }}</span>
                    <span class="text-sm opacity-70">{{ $item['price'] }} coins</span>
                    @if ($item['owned'])
                        <span class="text-xs uppercase opacity-60">Owned</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </main>
@endsection
