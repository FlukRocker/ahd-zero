@props(['items' => []])
<div class="-mx-6 flex snap-x snap-mandatory gap-5 overflow-x-auto px-6 pb-4 lg:-mx-10 lg:px-10" style="scrollbar-width: thin;">
    @foreach ($items as $item)
        <div class="w-[46vw] shrink-0 snap-start sm:w-[240px]">
            <x-poster-card :item="$item" />
        </div>
    @endforeach
</div>
