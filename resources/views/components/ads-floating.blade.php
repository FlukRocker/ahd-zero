@props(['payload' => null])

@php
    $d = $payload ?? ['left' => null, 'right' => null, 'bottom' => []];
    $rel = fn ($r) => ($r ?? '') !== '' ? $r : 'nofollow noopener sponsored noreferrer ugc';
    $has = ! empty($d['left']) || ! empty($d['right']) || ! empty($d['bottom']);
@endphp

@if ($has)
    <div x-data="{ show: true }" x-show="show" x-cloak class="floating-ads">
        @foreach (['left' => 'floating-l', 'right' => 'floating-r'] as $side => $cls)
            @if (! empty($d[$side]))
                @php $dim = \App\Support\AdImage::dimensions($d[$side]['src']); @endphp
                <div class="{{ $cls }}">
                    <button type="button" class="rail-close" aria-label="ปิดโฆษณา" @click="show = false">×</button>
                    <a href="{{ $d[$side]['href'] }}" rel="{{ $rel($d[$side]['rel'] ?? '') }}" target="_blank" class="rail-link">
                        <img src="{{ $d[$side]['src'] }}" alt="{{ $d[$side]['alt'] ?? '' }}"
                            @if ($dim) width="{{ $dim['w'] }}" height="{{ $dim['h'] }}" @else style="aspect-ratio: 160 / 600" @endif
                            loading="lazy" decoding="async">
                    </a>
                </div>
            @endif
        @endforeach

        @if (! empty($d['bottom']))
            <div class="floating-b">
                <button type="button" class="strip-close" aria-label="ปิดโฆษณา" @click="show = false">×</button>
                @foreach ($d['bottom'] as $it)
                    @php $dim = \App\Support\AdImage::dimensions($it['src']); @endphp
                    <a href="{{ $it['href'] }}" rel="{{ $rel($it['rel'] ?? '') }}" target="_blank" class="floating-b-item">
                        <img src="{{ $it['src'] }}" alt="{{ $it['alt'] ?? '' }}"
                            @if ($dim) width="{{ $dim['w'] }}" height="{{ $dim['h'] }}" @else style="aspect-ratio: 728 / 90" @endif
                            loading="lazy" decoding="async">
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
