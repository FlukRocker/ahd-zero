import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

function prefersReducedMotion() {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

/**
 * CSS-class-driven page transition. Toggle a class on the wrapper and let
 * the CSS `transition` property handle opacity + transform on the GPU. No
 * Motion call, no JS layout reads → no forced reflow on every Inertia
 * navigation.
 *
 * The class flow:
 *   add `page-tx-pre`         (initial hidden state, instant)
 *   next rAF: add `page-tx-in` (start CSS transition)
 *   on transitionend: clean both classes
 */
function playTransition(el: HTMLElement | null) {
    if (!el || prefersReducedMotion()) return;

    el.classList.add('page-tx-pre');
    requestAnimationFrame(() => {
        el.classList.add('page-tx-in');
    });

    const cleanup = () => {
        el.classList.remove('page-tx-pre');
        el.classList.remove('page-tx-in');
        el.removeEventListener('transitionend', cleanup);
    };
    el.addEventListener('transitionend', cleanup, { once: true });
    // Failsafe — if transitionend never fires (e.g. element hidden), drop
    // the classes so content stays visible.
    window.setTimeout(cleanup, 800);
}

/**
 * Run a fade + slide-up on the given element whenever Inertia finishes a visit
 * AND once on mount (first page load).
 */
export function usePageTransition(getEl: () => HTMLElement | null) {
    let unsubscribe: (() => void) | null = null;

    onMounted(() => {
        playTransition(getEl());
        unsubscribe = router.on('finish', () => playTransition(getEl()));
    });

    onBeforeUnmount(() => {
        unsubscribe?.();
    });
}
