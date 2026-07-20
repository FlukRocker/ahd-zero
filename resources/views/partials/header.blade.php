<header class="border-b border-[hsl(var(--border-ahd))] bg-[hsl(var(--bg-elev))]">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <a href="{{ url('/') }}" class="text-lg font-semibold text-[hsl(var(--fg))]">
            {{ $siteName }}
        </a>
        <button
            type="button"
            x-data
            @click="$store.appearance.toggle()"
            class="rounded-md px-3 py-1 text-sm text-[hsl(var(--fg-muted))] hover:text-[hsl(var(--fg))]"
            aria-label="Toggle theme"
        >
            ◐
        </button>
    </div>
</header>
