@php $items = $navbarAds ?? []; @endphp
@if (! empty($items))
    <div class="ads-navbar" role="navigation" aria-label="Sponsored links">
        <div class="mx-auto flex max-w-[1440px] flex-wrap items-center gap-x-5 gap-y-1 px-6 py-2 lg:px-10">
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase opacity-70">Sponsored</span>
            <ul class="flex flex-wrap items-center gap-x-4 gap-y-1">
                @foreach ($items as $it)
                    <li>
                        <a href="{{ $it['href'] }}" rel="{{ $it['rel'] ?: 'nofollow noopener sponsored noreferrer ugc' }}" target="_blank" class="text-[12px] font-medium hover:underline">{{ $it['alt'] }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
