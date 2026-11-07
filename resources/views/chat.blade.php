@extends('layouts.app')

@section('content')
    <main class="mx-auto w-full max-w-3xl px-4 py-6">
        <h1 class="text-xl font-semibold">Chat</h1>
        <ul id="chat-messages">
            @foreach ($history as $line)
                <li>{{ $line['name'] }}: {{ $line['body'] }}</li>
            @endforeach
        </ul>
    </main>
@endsection
