{{-- Site-wide announcement set in Chat management. x-text only. Loaded with the
     chat state, so it updates on the next page load, not live. --}}
<div class="flex items-start gap-2 border-b border-white/10 bg-sky-500/15 px-3 py-2 text-xs" x-show="announcement" x-cloak>
    <span aria-hidden="true">📢</span>
    <span class="min-w-0 flex-1" x-text="announcement"></span>
</div>
