// Builds the cosmetic `payload` from typed form fields. The keys and ranges
// mirror App\Support\CosmeticStyle / App\Rules\CosmeticPayload; the server is
// still the only authority and re-validates everything.

export interface PayloadFields {
    color?: string;
    from?: string;
    to?: string;
    angle?: number;
    strength?: number;
    code?: string;
    asset?: string;
    /** background only: true builds from/to/angle, false builds a colour. */
    gradient?: boolean;
    /** Optional extras on name types; empty means none. */
    icon?: string;
    anim?: string;
    /** Adds a glow colour + strength alongside a name colour or gradient. */
    extraGlow?: boolean;
    glowColor?: string;
    glowStrength?: number;
    /** Marks the item as a limited edition (a badge only; no stock or date). */
    limited?: boolean;
    /** Listed in the store but not buyable; admin grant only. */
    showOnly?: boolean;
    /** Units left to buy; undefined/null = unlimited. */
    stock?: number | null;
}

export const ICON_OPTIONS = [
    ['crown', '👑 Crown'],
    ['star', '⭐ Star'],
    ['heart', '❤️ Heart'],
    ['fire', '🔥 Fire'],
    ['bolt', '⚡ Bolt'],
    ['sparkle', '✨ Sparkle'],
    ['flower', '🌸 Flower'],
    ['skull', '💀 Skull'],
    ['moon', '🌙 Moon'],
    ['music', '🎵 Music'],
    ['diamond', '💎 Diamond'],
    ['ghost', '👻 Ghost'],
] as const;

export const ANIM_OPTIONS = [
    ['blink', 'Blink'],
    ['pulse', 'Pulse'],
    ['rainbow', 'Rainbow (hue cycle)'],
    ['shimmer', 'Shimmer (best on gradients)'],
    ['bounce', 'Bounce'],
] as const;

/** Types that accept the optional icon / animation extras. */
export function supportsExtras(type: string): boolean {
    return ['name_color', 'name_gradient', 'glow'].includes(type);
}

/** Types that can carry an additional glow (glow itself already is one). */
export function supportsExtraGlow(type: string): boolean {
    return ['name_color', 'name_gradient'].includes(type);
}

export type PayloadField = keyof PayloadFields;

const FIELDS: Record<string, PayloadField[]> = {
    name_color: ['color'],
    name_gradient: ['from', 'to', 'angle'],
    glow: ['color', 'strength'],
    smiley: ['code', 'asset'],
    pawn: ['asset'],
    background: ['color'],
};

export function fieldsFor(type: string, gradient = false): PayloadField[] {
    if (type === 'background' && gradient) {
        return ['from', 'to', 'angle'];
    }

    return Object.hasOwn(FIELDS, type) ? [...FIELDS[type]] : [];
}

export function buildPayload(
    type: string,
    f: PayloadFields,
): Record<string, unknown> {
    const out: Record<string, unknown> = {};

    for (const key of fieldsFor(type, f.gradient === true)) {
        const value = f[key] as string | number | undefined;

        if (key === 'angle' || key === 'strength') {
            out[key] = Math.trunc(Number(value ?? 0));
        } else {
            out[key] = value ?? '';
        }
    }

    if (supportsExtras(type)) {
        if (f.icon) {
            out.icon = f.icon;
        }

        if (f.anim) {
            out.anim = f.anim;
        }
    }

    if (supportsExtraGlow(type) && f.extraGlow) {
        out.glow = {
            color: f.glowColor ?? '#ffffff',
            strength: Math.trunc(Number(f.glowStrength ?? 2)),
        };
    }

    if (f.limited) {
        out.limited = true;
    }

    if (f.showOnly) {
        out.show_only = true;
    }

    if (f.stock !== undefined && f.stock !== null && Number.isFinite(Number(f.stock))) {
        out.stock = Math.max(0, Math.trunc(Number(f.stock)));
    }

    return out;
}
