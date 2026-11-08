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
): Record<string, string | number> {
    const out: Record<string, string | number> = {};

    for (const key of fieldsFor(type, f.gradient === true)) {
        const value = f[key] as string | number | undefined;

        if (key === 'angle' || key === 'strength') {
            out[key] = Math.trunc(Number(value ?? 0));
        } else {
            out[key] = value ?? '';
        }
    }

    return out;
}
