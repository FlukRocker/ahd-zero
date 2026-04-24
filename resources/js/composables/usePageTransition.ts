import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { animate } from 'motion';

function prefersReducedMotion() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function animateEl(el: HTMLElement | null) {
    if (!el || prefersReducedMotion()) return;
    animate(
        el,
        { opacity: [0, 1], y: [16, 0] },
        { duration: 0.45, easing: [0.2, 0.7, 0.2, 1] },
    );
}

/**
 * Run a fade + slide-up on the given element whenever Inertia finishes a visit
 * AND once on mount (first page load).
 */
export function usePageTransition(getEl: () => HTMLElement | null) {
    let unsubscribe: (() => void) | null = null;

    onMounted(() => {
        animateEl(getEl());
        unsubscribe = router.on('finish', () => animateEl(getEl()));
    });

    onBeforeUnmount(() => {
        unsubscribe?.();
    });
}
