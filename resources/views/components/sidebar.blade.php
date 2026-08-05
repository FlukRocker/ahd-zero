{{--
    Data comes from SidebarComposer (bound to this component only). This file
    runs no queries.

    $sidebarGenres      list<{slug, label}>
    $sidebarPopular     array<'7'|'30'|'all', list<card>>
    $sidebarHasPopular  bool — false when Mongo is down or has no rows
--}}
@php
    $windowLabels = ['7d' => '7 วัน', '30d' => '30 วัน', 'all' => 'ตลอดเวลา'];
@endphp

@if (! empty($sidebarGenres) || $sidebarHasPopular)
    <aside class="flex flex-col gap-8">
        @if (! empty($sidebarGenres))
            <section class="overflow-hidden rounded-2xl border"
                     style="border-color: hsl(var(--border-ahd)); background: hsl(var(--bg-elev));">
                <h2 class="px-4 py-3 font-mono text-[11px] tracking-[0.22em] uppercase"
                    style="background: hsl(var(--accent)); color: hsl(var(--accent-fg));">หมวดหมู่</h2>
                <nav class="flex flex-col p-2">
                    @foreach ($sidebarGenres as $genre)
                        <a href="/genre/{{ $genre['slug'] }}"
                           class="u-grow rounded-lg px-3 py-2 text-[13px]"
                           style="color: hsl(var(--fg-muted))">{{ $genre['label'] }}</a>
                    @endforeach
                </nav>
            </section>
        @endif

        @if ($sidebarHasPopular)
            <section class="overflow-hidden rounded-2xl border"
                     x-data="{ win: '7d' }"
                     style="border-color: hsl(var(--border-ahd)); background: hsl(var(--bg-elev));">
                <h2 class="px-4 py-3 font-mono text-[11px] tracking-[0.22em] uppercase"
                    style="background: hsl(var(--accent)); color: hsl(var(--accent-fg));">อนิเมะยอดนิยม</h2>

                {{-- All three lists are in the HTML; the tabs only toggle
                     visibility, so switching never waits on a fetch. --}}
                <div class="flex gap-1 p-2" role="tablist">
                    @foreach ($windowLabels as $key => $label)
                        <button type="button" role="tab"
                                x-on:click="win = '{{ $key }}'"
                                x-bind:aria-selected="win === '{{ $key }}' ? 'true' : 'false'"
                                class="flex-1 rounded-lg px-2 py-1.5 font-mono text-[11px]"
                                x-bind:style="win === '{{ $key }}'
                                    ? 'background: hsl(var(--bg-soft)); color: hsl(var(--fg)); font-weight: 500;'
                                    : 'color: hsl(var(--fg-faint));'">{{ $label }}</button>
                    @endforeach
                </div>

                @foreach ($sidebarPopular as $key => $items)
                    {{-- 7 วัน renders visible so the block still works with JS off. --}}
                    <ol class="flex flex-col gap-1 p-2 pt-0"
                        @if ($key !== '7d') style="display: none" @endif
                        x-show="win === '{{ $key }}'">
                        @foreach ($items as $i => $item)
                            <li>
                                <a href="{{ $item['href'] }}" class="group flex gap-3 rounded-lg p-2">
                                    <span class="mt-0.5 w-4 shrink-0 text-right font-mono text-[12px]"
                                          style="color: hsl(var(--fg-faint))">{{ $i + 1 }}</span>
                                    <img src="{{ \App\Support\Img::url($item['poster'], ['width' => 96, 'format' => 'webp']) ?? $item['poster'] }}"
                                         alt="{{ $item['title'] }}"
                                         loading="lazy" decoding="async" width="40" height="60"
                                         class="h-[60px] w-[40px] shrink-0 rounded-md object-cover">
                                    <span class="u-grow line-clamp-3 text-[12px] leading-snug"
                                          style="color: hsl(var(--fg-muted))">{{ $item['title'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @endforeach
            </section>
        @endif
    </aside>
@endif
