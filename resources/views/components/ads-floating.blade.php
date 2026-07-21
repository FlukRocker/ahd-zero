@props(['payload' => null])

@php
    $d = $payload ?? ['left' => null, 'right' => null, 'bottom' => []];
    $rel = fn ($r) => ($r ?? '') !== '' ? $r : 'nofollow noopener sponsored noreferrer ugc';
    $has = ! empty($d['left']) || ! empty($d['right']) || ! empty($d['bottom']);
@endphp

@if ($has)
    <div x-data="{ show: true }" x-show="show" x-cloak class="floating-ads">
        @if (! empty($d['left']))
            <div class="floating-l">
                <button type="button" class="rail-close" aria-label="ปิดโฆษณา" @click="show = false">×</button>
                <a href="{{ $d['left']['href'] }}" rel="{{ $rel($d['left']['rel'] ?? '') }}" target="_blank" class="rail-link">
                    <img src="{{ $d['left']['src'] }}" alt="{{ $d['left']['alt'] ?? '' }}" loading="lazy" decoding="async">
                </a>
            </div>
        @endif

        @if (! empty($d['right']))
            <div class="floating-r">
                <button type="button" class="rail-close" aria-label="ปิดโฆษณา" @click="show = false">×</button>
                <a href="{{ $d['right']['href'] }}" rel="{{ $rel($d['right']['rel'] ?? '') }}" target="_blank" class="rail-link">
                    <img src="{{ $d['right']['src'] }}" alt="{{ $d['right']['alt'] ?? '' }}" loading="lazy" decoding="async">
                </a>
            </div>
        @endif

        @if (! empty($d['bottom']))
            <div class="floating-b">
                <button type="button" class="strip-close" aria-label="ปิดโฆษณา" @click="show = false">×</button>
                @foreach ($d['bottom'] as $it)
                    <a href="{{ $it['href'] }}" rel="{{ $rel($it['rel'] ?? '') }}" target="_blank" class="floating-b-item">
                        <img src="{{ $it['src'] }}" alt="{{ $it['alt'] ?? '' }}" loading="lazy" decoding="async">
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
