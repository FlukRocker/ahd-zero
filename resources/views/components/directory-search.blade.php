@props(['action', 'query' => '', 'placeholder' => 'ค้นหา…'])

{{-- Progressive search: native GET form works without JS (type + Enter).
     Alpine adds debounced auto-submit as you type → full-page navigation. --}}
<form method="GET" action="{{ $action }}" x-data class="relative mt-8 max-w-md">
    <input
        type="text"
        name="q"
        value="{{ $query }}"
        @input.debounce.450ms="$root.requestSubmit()"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        aria-label="{{ $placeholder }}"
        class="w-full rounded-full px-5 py-3 text-[14px] outline-none"
        style="background: hsl(var(--bg-soft)); border: 1px solid hsl(var(--border-ahd));"
    >
</form>
