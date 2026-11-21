{{-- One fixed-size network unit in its own srcdoc iframe (so its `atOptions` global
     can't collide with the other units and the script stays out of the page). The
     box is exactly the creative's size: no layout shift. Lazy-loaded. --}}
@props(['name', 'fit' => false])
@php
    $u = config("ads.units.$name");
    $key = is_array($u) ? (string) ($u['key'] ?? '') : '';
    $show = config('ads.units_enabled') && $key !== '';
    if ($show) {
        $w = (int) $u['w'];
        $h = (int) $u['h'];
        $doc = '<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;padding:0;overflow:hidden;background:transparent}</style></head><body>'
            .'<script>atOptions='.json_encode(['key' => $key, 'format' => 'iframe', 'height' => $h, 'width' => $w, 'params' => new \stdClass], JSON_UNESCAPED_SLASHES).';</script>'
            .'<script src="'.e(sprintf((string) config('ads.network.src'), $key)).'"></script></body></html>';
    }
@endphp
@if ($show)
    <aside aria-label="Sponsored" {{ $attributes->merge(['class' => 'xu-unit']) }}>
        @if ($fit)
            {{-- Shrinks the fixed-size creative to the width available (phones), never enlarges it. --}}
            <div class="xu-fit" style="width:100%;max-width:{{ $w }}px;aspect-ratio:{{ $w }} / {{ $h }};overflow:hidden">
                <iframe srcdoc="{{ $doc }}" width="{{ $w }}" height="{{ $h }}" loading="lazy" scrolling="no" frameborder="0"
                    title="Sponsored" referrerpolicy="no-referrer-when-downgrade"
                    style="border:0;display:block;transform-origin:0 0"></iframe>
            </div>
        @else
            <iframe srcdoc="{{ $doc }}" width="{{ $w }}" height="{{ $h }}" loading="lazy" scrolling="no" frameborder="0"
                title="Sponsored" referrerpolicy="no-referrer-when-downgrade"
                style="border:0;display:block;max-width:100%"></iframe>
        @endif
    </aside>
    @if ($fit)
        <script>
            (function () {
                function fit() {
                    document.querySelectorAll('.xu-fit').forEach(function (b) {
                        var f = b.firstElementChild;
                        f.style.transform = 'scale(' + Math.min(1, b.clientWidth / {{ $w }}) + ')';
                    });
                }
                fit();
                addEventListener('resize', fit);
            })();
        </script>
    @endif
@endif
