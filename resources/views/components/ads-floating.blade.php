@props(['payload' => null])

@php
    $d = $payload ?? ['left' => null, 'right' => null, 'bottom' => []];
    $rel = fn ($r) => ($r ?? '') !== '' ? $r : 'nofollow noopener sponsored noreferrer ugc';
    $units = (bool) config('ads.units_enabled');
    $dock = $units && (string) config('ads.units.b320x50.key') !== '';
    $has = ! empty($d['left']) || ! empty($d['right']) || ! empty($d['bottom']) || $units;
@endphp

@if ($has)
    <div x-data="{ show: true }" x-show="show" x-cloak class="floating-ads">
        @foreach (['left' => 'floating-l', 'right' => 'floating-r'] as $side => $cls)
            @if (! empty($d[$side]))
                @php $dim = \App\Support\AdImage::dimensions($d[$side]['src']); @endphp
                <div class="{{ $cls }}">
                    <button type="button" class="rail-close" aria-label="ปิดโฆษณา" @click="show = false">×</button>
                    <a href="{{ $d[$side]['href'] }}" rel="{{ $rel($d[$side]['rel'] ?? '') }}" target="_blank" class="rail-link">
                        <img src="{{ $d[$side]['src'] }}" alt="{{ $d[$side]['alt'] ?? '' }}" loading="lazy"
                            @if ($dim) width="{{ $dim['w'] }}" height="{{ $dim['h'] }}" @else style="aspect-ratio: 160 / 600" @endif
                            decoding="async">
                    </a>
                </div>
            @endif
        @endforeach

        @if ($units)
            {{-- Wide screens only: bauval skyscrapers in the gutters beside the content. --}}
            <x-ads-unit name="b160x600" class="xu-rail" style="left:calc(50% - 720px - 176px)" />
            <x-ads-unit name="b160x300" class="xu-rail" style="left:calc(50% + 720px + 16px)" />
        @endif

        @if (! empty($d['bottom']) || $dock)
            <div class="floating-b {{ empty($d['bottom']) ? 'floating-b-solo' : '' }}">
                <button type="button" class="strip-close" aria-label="ปิดโฆษณา" @click="show = false">×</button>
                @foreach ($d['bottom'] as $it)
                    @php $dim = \App\Support\AdImage::dimensions($it['src']); @endphp
                    <a href="{{ $it['href'] }}" rel="{{ $rel($it['rel'] ?? '') }}" target="_blank" class="floating-b-item">
                        <img src="{{ $it['src'] }}" alt="{{ $it['alt'] ?? '' }}" loading="lazy"
                            @if ($dim) width="{{ $dim['w'] }}" height="{{ $dim['h'] }}" @else style="aspect-ratio: 728 / 90" @endif
                            decoding="async">
                    </a>
                @endforeach
                @if ($dock)
                    {{-- Same bar, same ×: the 320x50 network unit closes with the rest. --}}
                    <div class="floating-b-dock"><x-ads-unit name="b320x50" /></div>
                @endif
            </div>
        @endif
    </div>
@endif
