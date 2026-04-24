import { animate } from 'motion';
import { onBeforeUnmount, onMounted, type Ref } from 'vue';

function prefersReducedMotion() {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

export interface TiltOptions {
    max?: number;
    scale?: number;
    perspective?: number;
}

/**
 * Pointer-driven 3D tilt. Snaps back on leave. No-op if reduced motion.
 */
export function useTilt(
    target: Ref<HTMLElement | null>,
    options: TiltOptions = {},
) {
    const { max = 6, scale = 1.02, perspective = 800 } = options;
    let rafId = 0;
    let el: HTMLElement | null = null;

    function onMove(e: PointerEvent) {
        if (!el) return;
        const rect = el.getBoundingClientRect();
        const px = (e.clientX - rect.left) / rect.width;
        const py = (e.clientY - rect.top) / rect.height;
        const rx = (py - 0.5) * -max * 2;
        const ry = (px - 0.5) * max * 2;
        cancelAnimationFrame(rafId);
        rafId = requestAnimationFrame(() => {
            if (!el) return;
            el.style.transform = `perspective(${perspective}px) rotateX(${rx}deg) rotateY(${ry}deg) scale(${scale})`;
        });
    }

    function onLeave() {
        if (!el) return;
        cancelAnimationFrame(rafId);
        animate(
            el,
            {
                transform: `perspective(${perspective}px) rotateX(0deg) rotateY(0deg) scale(1)`,
            },
            { duration: 0.4, easing: [0.2, 0.7, 0.2, 1] },
        );
    }

    onMounted(() => {
        if (prefersReducedMotion()) return;
        el = target.value;
        if (!el) return;
        el.style.willChange = 'transform';
        el.addEventListener('pointermove', onMove);
        el.addEventListener('pointerleave', onLeave);
    });

    onBeforeUnmount(() => {
        cancelAnimationFrame(rafId);
        if (el) {
            el.removeEventListener('pointermove', onMove);
            el.removeEventListener('pointerleave', onLeave);
        }
    });
}
