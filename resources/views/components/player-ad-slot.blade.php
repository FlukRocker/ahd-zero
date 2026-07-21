@props(['ad' => null])

@if (! empty($ad))
    <a href="{{ $ad['href'] }}" rel="{{ ($ad['rel'] ?? '') ?: 'nofollow noopener sponsored noreferrer ugc' }}" target="_blank" {{ $attributes->merge(['class' => 'player-ad-slot block w-full overflow-hidden rounded-lg']) }} style="background: hsl(var(--bg-soft))">
        <img src="{{ $ad['src'] }}" alt="{{ $ad['alt'] ?? '' }}" class="block h-auto w-full" loading="lazy" decoding="async" referrerpolicy="no-referrer-when-downgrade">
    </a>
@endif
