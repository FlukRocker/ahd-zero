{{-- Online list, fed by the per-room chat.roster.{id} presence channel. x-text only. --}}
<aside class="rounded-xl border border-white/10 p-3">
    <h2 class="flex items-center justify-between text-xs font-semibold uppercase opacity-60">
        <span>Online</span>
        <span x-text="roster.length"></span>
    </h2>
    <ul class="mt-2 space-y-1 text-sm">
        <template x-for="person in roster" :key="person.uuid">
            <li class="flex items-center gap-2">
                <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                <span class="truncate" x-text="person.name"></span>
            </li>
        </template>
    </ul>
</aside>
