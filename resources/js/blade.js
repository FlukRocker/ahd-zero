import '../css/app.css';

import Alpine from 'alpinejs';
import { animate, inView } from 'motion';

// Expose for inline Blade usage and debugging.
window.Alpine = Alpine;
window.motionAnimate = animate;
window.motionInView = inView;

// In-view reveal: use in Blade as x-init="$reveal($el)" or
// x-init="$reveal($el, { y: 40, delay: 0.1 })".
// Mirrors the Motion-driven reveals used across ../lnw-anime.
Alpine.magic('reveal', () => (el, opts = {}) => {
    const { y = 24, duration = 0.5, delay = 0 } = opts;
    inView(el, () => {
        animate(
            el,
            {
                opacity: [0, 1],
                transform: [`translateY(${y}px)`, 'translateY(0)'],
            },
            { duration, delay, easing: [0.25, 0.46, 0.45, 0.94] },
        );
    });
});

// Appearance: the <head> FOUC script (in layouts/app.blade.php) already
// applies data-theme / data-density / data-type before paint from
// localStorage. This store exposes a runtime theme toggle for header/UI
// controls added in later phases.
Alpine.store('appearance', {
    get theme() {
        return document.documentElement.getAttribute('data-theme') || 'system';
    },
    setTheme(theme) {
        const resolved =
            theme === 'system'
                ? window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light'
                : theme;
        document.documentElement.setAttribute('data-theme', resolved);
        document.documentElement.classList.toggle('dark', resolved === 'dark');
        try {
            localStorage.setItem('appearance', theme);
        } catch {
            /* ignore — private mode / storage disabled */
        }
    },
    toggle() {
        const isDark = document.documentElement.classList.contains('dark');
        this.setTheme(isDark ? 'light' : 'dark');
    },
});

Alpine.start();
