import { describe, expect, it } from 'vitest';
import {
    buildPayload,
    fieldsFor,
} from '../../resources/js/lib/cosmeticPayload';

const all = {
    color: '#ff8800',
    from: '#111111',
    to: '#222222',
    angle: 90,
    strength: 2,
    code: '(wave)',
    asset: 'wave',
};

describe('buildPayload', () => {
    it('name_color has only a string colour', () => {
        const p = buildPayload('name_color', all);
        expect(Object.keys(p)).toEqual(['color']);
        expect(p.color).toBe('#ff8800');
    });

    it('name_gradient has from, to and an integer angle', () => {
        const p = buildPayload('name_gradient', { ...all, angle: 45.9 });
        expect(Object.keys(p).sort()).toEqual(['angle', 'from', 'to']);
        expect(p.angle).toBe(45);
        expect(typeof p.angle).toBe('number');
        expect(typeof p.from).toBe('string');
    });

    it('glow has a colour and an integer strength', () => {
        const p = buildPayload('glow', { ...all, strength: Number('3') });
        expect(Object.keys(p).sort()).toEqual(['color', 'strength']);
        expect(p.strength).toBe(3);
        expect(typeof p.strength).toBe('number');
    });

    it('smiley has a code and an asset', () => {
        const p = buildPayload('smiley', all);
        expect(Object.keys(p).sort()).toEqual(['asset', 'code']);
        expect(p).toEqual({ code: '(wave)', asset: 'wave' });
    });

    it('pawn and background have only an asset', () => {
        expect(buildPayload('pawn', all)).toEqual({ asset: 'wave' });
        expect(buildPayload('background', all)).toEqual({ asset: 'wave' });
    });

    it('test_buildPayload_ignores_fields_of_other_types', () => {
        expect(buildPayload('glow', all)).toEqual({
            color: '#ff8800',
            strength: 2,
        });
        expect(buildPayload('name_color', all)).not.toHaveProperty('from');
        expect(buildPayload('nope', all)).toEqual({});
    });
});

describe('fieldsFor', () => {
    it('test_fieldsFor_lists_only_that_types_inputs', () => {
        expect(fieldsFor('name_color')).toEqual(['color']);
        expect(fieldsFor('name_gradient')).toEqual(['from', 'to', 'angle']);
        expect(fieldsFor('glow')).toEqual(['color', 'strength']);
        expect(fieldsFor('smiley')).toEqual(['code', 'asset']);
        expect(fieldsFor('constructor')).toEqual([]);
    });
});
