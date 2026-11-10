@props(['bare' => false])
{{-- Online list for the whole site: members with any page open (heartbeat, see
     PresenceService), polled by the chat box. Highest rank first. x-text only;
     the list is empty for guests (the endpoint needs a member). --}}
<aside class="{{ $bare ? '' : 'rounded-2xl border border-white/10 bg-white/[0.03] ' }}p-3">
    <h2 class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider opacity-60">
        <span>Online</span>
        <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-emerald-400" x-show="config?.canPost" x-text="roster.length"></span>
    </h2>
    <ul class="mt-2 space-y-0.5 text-sm">
        <template x-for="person in sortedRoster()" :key="person.uuid">
            <li class="flex items-center gap-2 rounded px-1.5 py-0.5 hover:bg-white/5">
                {{-- Small person icon tinted by rank, like xat; no profile picture here. --}}
                <img x-show="pawnOf(person)" :src="pawnOf(person)" alt="" class="h-4 w-4 shrink-0" x-cloak>
                <svg x-show="!pawnOf(person)" viewBox="0 0 24 24" class="h-4 w-4 shrink-0" :style="{ color: ({ main_owner: '#f59e0b', owner: '#ef4444', mod: '#22c55e' })[person.rank] ?? '#a3a3a3' }" fill="currentColor" aria-hidden="true">
                    <circle cx="12" cy="7" r="4.5" /><path d="M3 22c0-5 4-8 9-8s9 3 9 8z" />
                </svg>
                <span class="chat-icon" x-show="iconOf(person)" :data-icon="iconOf(person)" aria-hidden="true"></span>
                <span
                    class="chat-name chat-online-name min-w-0 flex-1 truncate"
                    :data-anim="animOf(person)"
                    :class="{ 'chat-name-gradient': hasGradient(person) }"
                    :style="styleOf(person)"
                    x-text="person.name"
                ></span>
            </li>
        </template>
        <li class="px-1.5 py-1 text-xs opacity-50" x-show="roster.length === 0 && config?.canPost">ยังไม่มีผู้ออนไลน์</li>
        <li class="px-1.5 py-1 text-xs opacity-60" x-show="!config?.canPost">เข้าสู่ระบบเพื่อดูผู้ออนไลน์</li>
    </ul>
</aside>
