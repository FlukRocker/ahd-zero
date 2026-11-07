import { describe, expect, it } from 'vitest';
import chatLobby, { safeImage, safeParts, safeStyle } from '../../resources/js/chat/lobby';

describe('chat lobby rendering helpers', () => {
    it('keeps only allow-listed properties with safe values', () => {
        expect(
            safeStyle({
                '--chat-name-color': '#ff8800',
                '--chat-glow': '0 0 8px #00ff00',
                '--chat-pawn': "url('/x')",
                background: 'red',
            }),
        ).toEqual({ '--chat-name-color': '#ff8800', '--chat-glow': '0 0 8px #00ff00' });
    });

    it('drops values that could escape a CSS declaration', () => {
        expect(safeStyle({ '--chat-name-color': 'red;background:url(//evil)' })).toEqual({});
        expect(safeStyle({ '--chat-name-color': '</style>' })).toEqual({});
        expect(safeStyle(null)).toEqual({});
    });

    it('falls back to the body when a cached record has no parts', () => {
        expect(safeParts({ body: 'hi' })).toEqual([{ t: 'text', v: 'hi' }]);
    });

    it('only loads images from the pawn directory', () => {
        expect(safeImage({ t: 'img', v: '/chat/pawns/default.svg' })).toBe('/chat/pawns/default.svg');
        expect(safeImage({ t: 'img', v: '//evil.test/a.svg' })).toBeNull();
        expect(safeImage({ t: 'img', v: 'javascript:alert(1)' })).toBeNull();
        expect(safeImage({ t: 'text', v: 'x' })).toBeNull();
    });

    it('append skips a message whose id is already present', () => {
        const lobby = chatLobby() as any;
        lobby.$nextTick = () => {};
        lobby.$refs = { list: {} };
        lobby.append({ id: 1, body: 'a' });
        lobby.append({ id: 1, body: 'a' });
        lobby.append({ id: 2, body: 'b' });
        expect(lobby.messages.map((m: { id: number }) => m.id)).toEqual([1, 2]);
    });
});
