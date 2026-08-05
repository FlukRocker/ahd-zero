{{--
    Two-column wrapper. Main content is first in the DOM so it leads for
    crawlers and screen readers regardless of the sidebar sitting to its right.
    Under lg this is a single column and the sidebar stacks underneath — no
    drawer, no JS.

    If the sidebar renders nothing (no genres and no analytics), the second
    grid track collapses on its own and the content keeps the full width.
--}}
<div {{ $attributes->merge(['class' => 'mx-auto max-w-[1440px] px-6 lg:px-10']) }}>
    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0">
            {{ $slot }}
        </div>
        <x-sidebar />
    </div>
</div>
