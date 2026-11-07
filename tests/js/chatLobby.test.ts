// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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

    it('append dedupes a numeric id against the same id as a string', () => {
        const lobby = chatLobby() as any;
        lobby.$nextTick = () => {};
        lobby.$refs = { list: {} };
        lobby.append({ id: 7, body: 'a' });
        lobby.append({ id: '7', body: 'a' });
        expect(lobby.messages).toHaveLength(1);
    });
});

describe('chat lobby moderation', () => {
    const lines = () => [
        { id: 1, uuid: 'u1', rank: 'member', name: 'A', body: 'x' },
        { id: 2, uuid: 'me', rank: 'mod', name: 'Me', body: 'y' },
    ];

    function make(myRank = 'owner') {
        const lobby = chatLobby() as any;
        lobby.config = { me: 'me', myRank, canPost: true };
        lobby.messages = lines();
        return lobby;
    }

    function respond(status: number, body: unknown) {
        const fetchMock = vi.fn(async () => ({ ok: status >= 200 && status < 300, status, json: async () => body }));
        vi.stubGlobal('fetch', fetchMock);
        return fetchMock;
    }

    beforeEach(() => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 't';
        document.head.appendChild(meta);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.head.innerHTML = '';
    });

    it('test_moderate_posts_the_right_body_and_shows_the_server_refusal_inline', async () => {
        const fetchMock = respond(403, { message: 'Your rank does not allow that.' });
        const lobby = make();
        await lobby.moderate(lobby.messages[0], 'gag');
        const [url, init] = fetchMock.mock.calls[0] as any;
        expect(url).toBe('/chat/moderate');
        expect(init.method).toBe('POST');
        expect(init.headers['X-CSRF-TOKEN']).toBe('t');
        expect(JSON.parse(init.body)).toEqual({ action: 'gag', target_uuid: 'u1', minutes: 10 });
        expect(lobby.error).toBe('Your rank does not allow that.');
        expect(lobby.messages).toHaveLength(2);
    });

    it('test_kick_sends_no_minutes', async () => {
        const fetchMock = respond(200, { ok: true });
        const lobby = make();
        await lobby.moderate(lobby.messages[0], 'kick');
        expect(JSON.parse((fetchMock.mock.calls[0] as any)[1].body)).toEqual({ action: 'kick', target_uuid: 'u1' });
    });

    it('test_double_click_sends_one_request', async () => {
        const fetchMock = respond(200, { ok: true });
        const lobby = make();
        const a = lobby.moderate(lobby.messages[0], 'gag');
        const b = lobby.moderate(lobby.messages[0], 'gag');
        await Promise.all([a, b]);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(lobby.busy['1gag']).toBeFalsy();
    });

    it('test_ban_asks_for_a_reason_and_cancel_sends_nothing', async () => {
        const fetchMock = respond(200, { ok: true });
        const lobby = make();
        vi.stubGlobal('window', Object.assign(window, { prompt: vi.fn(() => null), confirm: vi.fn(() => true) }));
        await lobby.moderate(lobby.messages[0], 'ban');
        expect(window.prompt).toHaveBeenCalled();
        expect(fetchMock).not.toHaveBeenCalled();

        (window.prompt as any).mockReturnValue('spam');
        (window.confirm as any).mockReturnValue(false);
        await lobby.moderate(lobby.messages[0], 'ban');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('test_ban_sends_a_timed_ban', async () => {
        const fetchMock = respond(200, { ok: true });
        const lobby = make();
        const confirmMock = vi.fn(() => true);
        vi.stubGlobal('window', Object.assign(window, { prompt: vi.fn(() => 'spam'), confirm: confirmMock }));
        await lobby.moderate(lobby.messages[0], 'ban');
        expect(confirmMock.mock.calls[0][0]).toContain('24 hours');
        expect(JSON.parse((fetchMock.mock.calls[0] as any)[1].body)).toEqual({
            action: 'ban',
            target_uuid: 'u1',
            reason: 'spam',
            minutes: 1440,
        });
    });

    it('test_delete_removes_the_line_only_after_ok', async () => {
        const lobby = make();
        vi.stubGlobal('window', Object.assign(window, { confirm: vi.fn(() => true) }));
        const fetchMock = respond(403, { message: 'You cannot delete that message.' });
        await lobby.moderate(lobby.messages[0], 'delete');
        expect((fetchMock.mock.calls[0] as any)[0]).toBe('/chat/messages/1');
        expect((fetchMock.mock.calls[0] as any)[1].method).toBe('DELETE');
        expect(lobby.error).toBe('You cannot delete that message.');
        expect(lobby.messages).toHaveLength(2);

        respond(200, { deleted: true });
        await lobby.moderate(lobby.messages[0], 'delete');
        expect(lobby.messages.map((m: { id: number }) => m.id)).toEqual([2]);
    });

    it('test_delete_confirms_only_for_someone_elses_message', async () => {
        respond(200, { deleted: true });
        const confirmMock = vi.fn(() => false);
        vi.stubGlobal('window', Object.assign(window, { confirm: confirmMock }));
        const lobby = make();
        await lobby.moderate(lobby.messages[0], 'delete');
        expect(confirmMock).toHaveBeenCalledTimes(1);
        expect(lobby.messages).toHaveLength(2);

        await lobby.moderate(lobby.messages[1], 'delete');
        expect(confirmMock).toHaveBeenCalledTimes(1);
        expect(lobby.messages.map((m: { id: number }) => m.id)).toEqual([1]);
    });

    it('test_429_without_a_message_falls_back', async () => {
        respond(429, {});
        const lobby = make();
        await lobby.moderate(lobby.messages[0], 'gag');
        expect(lobby.error).toBe('Slow down a moment.');
    });

    it('test_member_sanctioned_gag_for_me_sets_a_notice', () => {
        const lobby = make();
        lobby.onSanctioned({ uuid: 'me', action: 'gag' }, { leave: vi.fn() });
        expect(lobby.notice).toBe('A moderator gagged you for a while.');
        lobby.onSanctioned({ uuid: 'me', action: 'ungag' }, { leave: vi.fn() });
        expect(lobby.notice).toBe('');
        lobby.onSanctioned({ uuid: 'other', action: 'gag' }, { leave: vi.fn() });
        expect(lobby.notice).toBe('');
    });
});
