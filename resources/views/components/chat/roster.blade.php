@props(['bare' => false])
{{-- Online list, fed by the per-room chat.roster.{id} presence channel. Highest
     rank first. x-text only; the list is empty for guests (the presence channel
     needs a member). --}}
<aside class="{{ $bare ? '' : 'rounded-2xl border border-white/10 bg-white/[0.03] ' }}p-3">
    <h2 class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider opacity-60">
        <span>Online</span>
        <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-emerald-400" x-text="roster.length"></span>
    </h2>
    <ul class="mt-2 space-y-0.5 text-sm">
        <template x-for="person in sortedRoster()" :key="person.uuid">
            <li class="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-white/5">
                <span class="chat-avatar relative flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold uppercase" :data-rank="person.rank">
                    <span x-text="person.name.charAt(0)"></span>
                    <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-neutral-900 bg-emerald-500" aria-hidden="true"></span>
                </span>
                <span class="min-w-0 flex-1 truncate" x-text="person.name"></span>
                <span class="chat-badge" :data-rank="person.rank" x-show="({ main_owner: 'Owner', owner: 'Admin', mod: 'Mod' })[person.rank]" x-text="({ main_owner: 'Owner', owner: 'Admin', mod: 'Mod' })[person.rank]"></span>
            </li>
        </template>
        <li class="px-1.5 py-1 text-xs opacity-50" x-show="roster.length === 0">ยังไม่มีผู้ออนไลน์</li>
    </ul>
</aside>
