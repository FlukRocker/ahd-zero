{{-- Network banner slots (728x90). Each slot is its own srcdoc iframe so the
     per-slot `atOptions` global can't be clobbered by its siblings, and the
     third-party script can't touch the page. Fixed box = zero CLS; lazy-loaded. --}}
@props(['count' => null])

@php
    $cfg = config('ads.network');
    $key = (string) ($cfg['key'] ?? '');
    $w = (int) $cfg['width'];
    $h = (int) $cfg['height'];
    $n = $count ?? (int) $cfg['slots'];
    $src = sprintf((string) $cfg['src'], $key);
    $doc = '<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;padding:0;overflow:hidden;background:transparent}</style></head><body>'
        .'<script>atOptions='.json_encode(['key' => $key, 'format' => 'iframe', 'height' => $h, 'width' => $w, 'params' => new \stdClass], JSON_UNESCAPED_SLASHES).';</script>'
        .'<script src="'.e($src).'"></script></body></html>';
@endphp

@if ($key !== '' && $n > 0)
    <aside class="ads-network mx-auto flex flex-col items-center gap-2" aria-label="Sponsored" style="max-width: {{ $w }}px">
        @for ($i = 0; $i < $n; $i++)
            <div class="ads-network-slot w-full overflow-hidden" style="aspect-ratio: {{ $w }} / {{ $h }}">
                <iframe srcdoc="{{ $doc }}" width="{{ $w }}" height="{{ $h }}" loading="lazy" scrolling="no"
                    frameborder="0" title="Sponsored" referrerpolicy="no-referrer-when-downgrade"
                    style="border:0;display:block;transform-origin:0 0"></iframe>
            </div>
        @endfor
    </aside>
    {{-- Shrink the fixed-size creative to fit narrow screens (it can't reflow). --}}
    <script>
        (function () {
            function fit() {
                document.querySelectorAll('.ads-network-slot').forEach(function (s) {
                    var f = s.firstElementChild, k = Math.min(1, s.clientWidth / {{ $w }});
                    f.style.transform = 'scale(' + k + ')';
                });
            }
            fit();
            addEventListener('resize', fit);
        })();
    </script>
@endif
