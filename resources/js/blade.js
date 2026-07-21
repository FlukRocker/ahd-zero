import '../css/app.css';

import Alpine from 'alpinejs';

// Expose Alpine for inline Blade usage and debugging.
window.Alpine = Alpine;

// NOTE: Motion (`motion` package, ~130KB) is intentionally NOT imported here.
// The Blade site is server-rendered and static per the SEO decision — no
// scroll-reveal / tilt / parallax. Alpine alone drives the interactive chrome
// (theme toggle, search overlay, drawers, dropdowns). If a future page needs
// animation, lazy-load `motion` on that page only rather than in this global
// entry, so it never lands on the critical path of read pages.

// Appearance store. The <head> FOUC script (layouts/app.blade.php) already
// applied data-theme / .dark before paint from localStorage; this store reads
// that initial state into a reactive `isDark` and keeps it in sync so header
// controls (theme toggle icon) update reactively.
Alpine.store('appearance', {
    isDark: false,
    init() {
        this.isDark = document.documentElement.classList.contains('dark');
    },
    apply(theme) {
        const resolved =
            theme === 'system'
                ? window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light'
                : theme;
        document.documentElement.setAttribute('data-theme', resolved);
        document.documentElement.classList.toggle('dark', resolved === 'dark');
        this.isDark = resolved === 'dark';
        try {
            localStorage.setItem('appearance', theme);
        } catch {
            /* ignore — private mode / storage disabled */
        }
    },
    toggle() {
        this.apply(this.isDark ? 'light' : 'dark');
    },
});

Alpine.start();

// Deferred ad loading. Ad <img>s render with `data-ad-src` (no `src`) and
// explicit width/height so they reserve exact space (zero CLS) but do NOT load
// during the initial/LCP window — a heavy third-party ad creative can't become
// the LCP element. After the page settles (idle), an IntersectionObserver
// swaps data-ad-src -> src as each ad nears the viewport, so above-the-fold ads
// appear right after LCP and the rest load on scroll. No ad on the critical path.
function initDeferredAds() {
    const imgs = document.querySelectorAll('img[data-ad-src]');
    if (!imgs.length) return;

    const load = (img) => {
        img.src = img.dataset.adSrc;
        img.removeAttribute('data-ad-src');
    };

    if (!('IntersectionObserver' in window)) {
        imgs.forEach(load);
        return;
    }

    const io = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    load(e.target);
                    obs.unobserve(e.target);
                }
            });
        },
        { rootMargin: '400px' },
    );
    imgs.forEach((img) => io.observe(img));
}

if (typeof window !== 'undefined') {
    const schedule = () =>
        'requestIdleCallback' in window
            ? requestIdleCallback(initDeferredAds, { timeout: 2500 })
            : setTimeout(initDeferredAds, 1200);
    if (document.readyState === 'complete') schedule();
    else window.addEventListener('load', schedule, { once: true });
}
