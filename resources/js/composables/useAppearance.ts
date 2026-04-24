import { onMounted, reactive, ref, watch } from 'vue';

export type Appearance = 'light' | 'dark' | 'system';
export type Accent = 'rose' | 'coral' | 'violet' | 'sky' | 'mint' | 'honey';
export type Density = 'comfy' | 'compact';
export type TypePairing = 'instrument' | 'fraunces';

export const ACCENTS: Record<Accent, string> = {
    rose: '350 80% 68%',
    coral: '18 85% 65%',
    violet: '275 65% 70%',
    sky: '205 75% 65%',
    mint: '160 50% 60%',
    honey: '42 85% 62%',
};

export interface DesignConfig {
    accent: Accent;
    density: Density;
    typePairing: TypePairing;
}

const DEFAULT_CONFIG: DesignConfig = {
    accent: 'rose',
    density: 'comfy',
    typePairing: 'instrument',
};

const STORAGE_KEYS = {
    appearance: 'appearance',
    config: 'ahd.config',
};

const isBrowser = () => typeof window !== 'undefined';

function setCookie(name: string, value: string, days = 365) {
    if (typeof document === 'undefined') return;
    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

function mediaQuery() {
    return isBrowser()
        ? window.matchMedia('(prefers-color-scheme: dark)')
        : null;
}

function resolveTheme(value: Appearance): 'light' | 'dark' {
    if (value === 'system') {
        return mediaQuery()?.matches ? 'dark' : 'light';
    }
    return value;
}

export function updateTheme(value: Appearance) {
    if (!isBrowser()) return;
    const resolved = resolveTheme(value);
    const root = document.documentElement;
    root.classList.toggle('dark', resolved === 'dark');
    root.setAttribute('data-theme', resolved);
}

export function applyDesignConfig(config: DesignConfig) {
    if (!isBrowser()) return;
    const root = document.documentElement;
    root.setAttribute(
        'data-type',
        config.typePairing === 'fraunces' ? 'alt' : 'default',
    );
    root.setAttribute('data-density', config.density);
    const accent = ACCENTS[config.accent] ?? ACCENTS.rose;
    root.style.setProperty('--accent', accent);
}

function getStoredAppearance(): Appearance | null {
    if (!isBrowser()) return null;
    return localStorage.getItem(STORAGE_KEYS.appearance) as Appearance | null;
}

function getStoredConfig(): DesignConfig {
    if (!isBrowser()) return { ...DEFAULT_CONFIG };
    try {
        const raw = localStorage.getItem(STORAGE_KEYS.config);
        if (!raw) return { ...DEFAULT_CONFIG };
        return { ...DEFAULT_CONFIG, ...JSON.parse(raw) };
    } catch {
        return { ...DEFAULT_CONFIG };
    }
}

function handleSystemThemeChange() {
    updateTheme(getStoredAppearance() || 'system');
}

export function initializeTheme() {
    if (!isBrowser()) return;
    updateTheme(getStoredAppearance() || 'system');
    applyDesignConfig(getStoredConfig());
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

// Shared reactive state across components
const appearance = ref<Appearance>('system');
const designConfig = reactive<DesignConfig>({ ...DEFAULT_CONFIG });
let hydrated = false;

function hydrate() {
    if (hydrated || !isBrowser()) return;
    const saved = getStoredAppearance();
    if (saved) appearance.value = saved;
    Object.assign(designConfig, getStoredConfig());
    hydrated = true;
}

export function useAppearance() {
    onMounted(hydrate);

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem(STORAGE_KEYS.appearance, value);
        setCookie(STORAGE_KEYS.appearance, value);
        updateTheme(value);
    }

    function updateConfig<K extends keyof DesignConfig>(
        key: K,
        value: DesignConfig[K],
    ) {
        designConfig[key] = value;
        localStorage.setItem(STORAGE_KEYS.config, JSON.stringify(designConfig));
        applyDesignConfig(designConfig);
    }

    function toggleTheme() {
        const resolved = resolveTheme(appearance.value);
        updateAppearance(resolved === 'dark' ? 'light' : 'dark');
    }

    return {
        appearance,
        designConfig,
        updateAppearance,
        updateConfig,
        toggleTheme,
    };
}

// Keep changes in sync if multiple components call updateConfig at once
watch(
    designConfig,
    (cfg) => {
        if (isBrowser()) applyDesignConfig(cfg);
    },
    { deep: true },
);
