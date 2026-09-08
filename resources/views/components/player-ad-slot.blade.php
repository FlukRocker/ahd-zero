{{-- `ads` is a list of banners stacked in order; `ad` stays for single-banner callers. --}}
@props(['ad' => null, 'ads' => null])

@php
    $items = $ads ?? ($ad !== null ? [$ad] : []);
    $items = array_values(array_filter((array) $items));
@endphp

@if (! empty($items))
    <div {{ $attributes->merge(['class' => 'player-ad-stack flex flex-col gap-3']) }}>
        @foreach ($items as $item)
            @php $d = \App\Support\AdImage::dimensions($item['src']); @endphp
            <a href="{{ $item['href'] }}" rel="{{ ($item['rel'] ?? '') ?: 'nofollow noopener sponsored noreferrer ugc' }}" target="_blank" class="player-ad-slot block w-full overflow-hidden rounded-lg" style="background: hsl(var(--bg-soft))">
                <img src="{{ $item['src'] }}" alt="{{ $item['alt'] ?? '' }}" class="block w-full" loading="lazy"
                    @if ($d) width="{{ $d['w'] }}" height="{{ $d['h'] }}" style="height:auto" @else style="aspect-ratio: 728 / 90; height: auto" @endif
                    decoding="async" referrerpolicy="no-referrer-when-downgrade">
            </a>
        @endforeach
    </div>
@endif
