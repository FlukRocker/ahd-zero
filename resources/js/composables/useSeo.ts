import { usePage } from '@inertiajs/vue3';
import { useHead, useSeoMeta } from '@unhead/vue';
import { computed } from 'vue';

interface SharedProps {
    name?: string;
    appUrl?: string;
}

export function useAppMeta() {
    const page = usePage<SharedProps>();
    const appName = computed(() => page.props.name ?? 'Anime HD Zero');
    const appUrl = computed(() => (page.props.appUrl ?? '').replace(/\/$/, ''));
    const currentPath = computed(() => page.url);
    const canonical = computed(() => {
        const base =
            appUrl.value ||
            (typeof window !== 'undefined' ? window.location.origin : '');
        if (!base) return currentPath.value;
        // Strip query for canonical unless page is search
        const path = currentPath.value.split('?')[0];
        return `${base}${path}`;
    });

    return { appName, appUrl, currentPath, canonical };
}

export interface SeoInput {
    title?: string;
    description?: string;
    image?: string | null;
    type?: 'website' | 'article' | 'video.tv_show' | 'video.episode';
    robots?: string;
    schema?: Array<{ type: 'application/ld+json'; innerHTML: string }>;
}

/**
 * Consolidate per-page SEO: title, meta, OG, Twitter, canonical, JSON-LD.
 */
export function useSeo(input: () => SeoInput) {
    const { appName, canonical } = useAppMeta();

    useHead(() => {
        const cfg = input();
        const title = cfg.title
            ? `${cfg.title} — ${appName.value}`
            : appName.value;

        return {
            link: [{ rel: 'canonical', href: canonical.value }],
            ...(cfg.schema?.length ? { script: cfg.schema } : {}),
        };
    });

    useSeoMeta(() => {
        const cfg = input();
        const title = cfg.title
            ? `${cfg.title} — ${appName.value}`
            : appName.value;
        return {
            title,
            description: cfg.description,
            robots: cfg.robots,
            ogTitle: title,
            ogDescription: cfg.description,
            ogType: cfg.type ?? 'website',
            ogImage: cfg.image ?? undefined,
            ogUrl: canonical.value,
            twitterCard: 'summary_large_image',
            twitterTitle: title,
            twitterDescription: cfg.description,
            twitterImage: cfg.image ?? undefined,
        };
    });
}
