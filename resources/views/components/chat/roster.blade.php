{{-- Online list, fed by the chat.roster presence channel. x-text only. --}}
<aside class="hidden w-56 rounded-xl border border-white/10 p-3 lg:block">
    <h2 class="text-xs font-semibold uppercase opacity-60">Online</h2>
    <ul class="mt-2 space-y-1 text-sm">
        <template x-for="person in roster" :key="person.uuid">
            <li x-text="person.name"></li>
        </template>
    </ul>
</aside>
