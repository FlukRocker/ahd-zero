{{-- Online list, fed by the per-room chat.roster.{id} presence channel. x-text only. --}}
<aside class="rounded-xl border border-white/10 p-3">
    <h2 class="text-xs font-semibold uppercase opacity-60">Online</h2>
    <ul class="mt-2 space-y-1 text-sm">
        <template x-for="person in roster" :key="person.uuid">
            <li x-text="person.name"></li>
        </template>
    </ul>
</aside>
