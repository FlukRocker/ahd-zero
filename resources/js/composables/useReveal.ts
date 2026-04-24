import { onBeforeUnmount, onMounted, type Ref } from 'vue';
import { animate, stagger, inView } from 'motion';

function prefersReducedMotion() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

export interface RevealOptions {
    selector?: string;
    y?: number;
    duration?: number;
    delay?: number;
    once?: boolean;
}

/**
 * Fade + slide-up reveal on scroll using Motion One's inView.
 * Pass a root element ref (template ref) or rely on document.
 *
 * Critical: this composable hides targets via inline JS *only* when motion is
 * supported. The `.reveal` class itself does NOT hide content — so if the
 * observer never fires (or JS fails), content stays visible.
 */
export function useReveal(options: RevealOptions = {}) {
    const {
        selector = '.reveal',
        y = 24,
        duration = 0.7,
        delay = 0,
        once = true,
    } = options;

    const stops: Array<() => void> = [];

    function init(root: HTMLElement | Document = document) {
        if (prefersReducedMotion()) return;

        const targets = root.querySelectorAll<HTMLElement>(selector);
        targets.forEach((target) => {
            // Pre-hide right before observing. Done synchronously so the
            // browser doesn't paint the visible state first.
            target.style.opacity = '0';
            target.style.transform = `translateY(${y}px)`;
            target.style.willChange = 'opacity, transform';

            // Failsafe: if for any reason inView never fires within 1.2s, force
            // the element visible. Better to skip the animation than hide data.
            const failsafe = window.setTimeout(() => {
                target.style.opacity = '1';
                target.style.transform = '';
                target.style.willChange = '';
            }, 1200);

            const stop = inView(
                target,
                () => {
                    window.clearTimeout(failsafe);
                    animate(
                        target,
                        { opacity: [0, 1], y: [y, 0] },
                        { duration, delay, easing: [0.2, 0.7, 0.2, 1] },
                    );
                    return () => {
                        if (!once) {
                            animate(target, { opacity: [1, 0], y: [0, y] }, { duration: 0.3 });
                        }
                    };
                },
                { amount: 0.1 },
            );
            stops.push(() => {
                window.clearTimeout(failsafe);
                stop();
            });
        });
    }

    function teardown() {
        stops.forEach((s) => s());
        stops.length = 0;
    }

    return { init, teardown };
}

/**
 * Stagger an arbitrary set of elements (e.g. hero content) immediately.
 */
export function animateStagger(selector: string, root: ParentNode = document) {
    const els = Array.from(root.querySelectorAll<HTMLElement>(selector));
    if (!els.length) return;
    animate(
        els,
        { opacity: [0, 1], y: [24, 0] },
        { duration: 0.8, delay: stagger(0.08), easing: [0.2, 0.7, 0.2, 1] },
    );
}

/**
 * Auto-wire reveal on mount for a component.
 */
export function useAutoReveal(options: RevealOptions = {}) {
    const { init, teardown } = useReveal(options);
    onMounted(() => init(document));
    onBeforeUnmount(teardown);
}

/**
 * Stagger children of a container element when it enters the viewport.
 */
export function useStaggerInView(
    containerRef: Ref<HTMLElement | null>,
    options: { childSelector?: string; y?: number; duration?: number; gap?: number } = {},
) {
    const { childSelector = ':scope > *', y = 18, duration = 0.6, gap = 0.05 } = options;
    let stop: (() => void) | null = null;
    let failsafe: number | null = null;

    onMounted(() => {
        const el = containerRef.value;
        if (!el || prefersReducedMotion()) return;

        const children = Array.from(el.querySelectorAll<HTMLElement>(childSelector));
        if (!children.length) return;

        children.forEach((c) => {
            c.style.opacity = '0';
            c.style.transform = `translateY(${y}px)`;
        });

        // Failsafe: force-show children after 1.2s if observer never fires.
        failsafe = window.setTimeout(() => {
            children.forEach((c) => {
                c.style.opacity = '1';
                c.style.transform = '';
            });
        }, 1200);

        stop = inView(
            el,
            () => {
                if (failsafe !== null) {
                    window.clearTimeout(failsafe);
                    failsafe = null;
                }
                animate(
                    children,
                    { opacity: [0, 1], y: [y, 0] },
                    { duration, delay: stagger(gap), easing: [0.2, 0.7, 0.2, 1] },
                );
            },
            { amount: 0.05 },
        );
    });

    onBeforeUnmount(() => {
        if (failsafe !== null) window.clearTimeout(failsafe);
        stop?.();
    });
}
