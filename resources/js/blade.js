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
