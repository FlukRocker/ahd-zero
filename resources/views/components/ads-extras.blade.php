{{-- Side rails (wide screens only) and the sticky 320x50 bar. Rendered once, from the layout. --}}
@if (config('ads.units_enabled'))
    <style>
        .xu-unit{display:flex;justify-content:center}
        .xu-rail{position:fixed;top:96px;z-index:20;display:none}
        .xu-468{display:none}
        .xu-dock{position:fixed;left:0;right:0;bottom:0;z-index:40;display:flex;justify-content:center;pointer-events:none}
        .xu-dock-in{position:relative;pointer-events:auto}
        .xu-dock-x{position:absolute;right:-8px;top:-8px;z-index:1;width:22px;height:22px;border-radius:9999px;border:1px solid hsl(var(--border,0 0% 50%));background:hsl(var(--bg,0 0% 100%));color:hsl(var(--fg,0 0% 0%));font-size:13px;line-height:1;cursor:pointer;padding:0}
        @media (min-width:640px){.xu-468{display:flex}}
        @media (min-width:1800px){.xu-rail{display:block}}
    </style>
    <x-ads-unit name="b160x600" class="xu-rail" style="left:calc(50% - 720px - 176px)" />
    <x-ads-unit name="b160x300" class="xu-rail" style="left:calc(50% + 720px + 16px)" />
    @if ((string) config('ads.units.b320x50.key') !== '')
        <div class="xu-dock" id="xu-dock">
            <div class="xu-dock-in">
                <button type="button" class="xu-dock-x" aria-label="ปิดโฆษณา" onclick="document.getElementById('xu-dock').style.display='none'">×</button>
                <x-ads-unit name="b320x50" />
            </div>
        </div>
    @endif
@endif
