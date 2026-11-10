// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import chatLobby, { plainText, safeAvatar, safeReply, safeEmbed, safeImage, safeParts, safeStyle } from '../../resources/js/chat/lobby';

const echoCalls = vi.hoisted(() => ({ channel: [] as string[], join: [] as string[], leave: [] as string[] }));

vi.mock('laravel-echo', () => ({
    default: class {
        channel(name: string) {
            echoCalls.channel.push(name);
            const chain: any = { listen: () => chain };
            return chain;
        }
        join(name: string) {
            echoCalls.join.push(name);
            const chain: any = { here: () => chain, joining: () => chain, leaving: () => chain };
            return chain;
        }
        leave(name: string) {
            echoCalls.leave.push(name);
        }
    },
}));
vi.mock('pusher-js', () => ({ default: class {} }));

describe('chat lobby rooms', () => {
    afterEach(() => {
        vi.unstubAllEnvs();
        vi.unstubAllGlobals();
        echoCalls.channel.length = 0;
        echoCalls.join.length = 0;
        echoCalls.leave.length = 0;
        document.head.innerHTML = '';
    });

    function room(canPost = true) {
        const lobby = chatLobby() as any;
        lobby.config = { me: 'me', myRank: 'member', canPost, room: { id: 7, slug: 'anime', name: 'Anime' } };
        return lobby;
    }

    it('connect() subscribes to the presence and per-room channels', async () => {
        vi.stubEnv('VITE_REVERB_APP_KEY', 'k');
        vi.stubEnv('VITE_REVERB_HOST', 'h');
        await room().connect();
        expect(echoCalls.channel).toEqual(['chat.presence', 'chat.room.7']);
        expect(echoCalls.join).toEqual([]);
    });

    it('send() includes room_id', async () => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 't';
        document.head.appendChild(meta);
        const fetchMock = vi.fn(async () => ({ ok: true, status: 200, json: async () => null }));
        vi.stubGlobal('fetch', fetchMock);
        const lobby = room();
        lobby.draft = 'hello';
        await lobby.send();
        expect(JSON.parse((fetchMock.mock.calls[0] as any)[1].body)).toEqual({ body: 'hello', room_id: 7 });
    });

    it('onSanctioned drops a banned member from the roster', () => {
        const lobby = room();
        lobby.roster = [{ uuid: 'me', name: 'Me', rank: 'member' }, { uuid: 'x', name: 'X', rank: 'member' }];
        lobby.onSanctioned({ uuid: 'me', action: 'ban' }, {});
        expect(lobby.roster.map((p: any) => p.uuid)).toEqual(['x']);
    });

    it('refreshRoster() takes the site-wide online list from /chat/online', async () => {
        const fetchMock = vi.fn(async () => ({ ok: true, json: async () => ({ online: [{ uuid: 'a', name: 'A', rank: 'member' }] }) }));
        vi.stubGlobal('fetch', fetchMock);
        const lobby = room();
        await lobby.refreshRoster();
        expect((fetchMock.mock.calls[0] as any)[0]).toBe('/chat/online');
        expect(lobby.roster).toEqual([{ uuid: 'a', name: 'A', rank: 'member' }]);
    });

    it('refreshRoster() keeps the last list when the request fails', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => { throw new Error('offline'); }));
        const lobby = room();
        lobby.roster = [{ uuid: 'a', name: 'A', rank: 'member' }];
        await lobby.refreshRoster();
        expect(lobby.roster).toHaveLength(1);
    });

    it('safeStyle keeps only allow-listed icon and animation keywords', () => {
        expect(safeStyle({ '--chat-icon': 'crown', '--chat-anim': 'rainbow' })).toEqual({ '--chat-icon': 'crown', '--chat-anim': 'rainbow' });
        expect(safeStyle({ '--chat-icon': 'evil-icon', '--chat-anim': 'spin' })).toEqual({});
        const lobby = room();
        expect(lobby.iconOf({ style: { '--chat-icon': 'star' } })).toBe('star');
        expect(lobby.animOf({ style: { '--chat-anim': 'nope' } })).toBe('');
    });

    it('pawnOf() only accepts the vetted pawn path', () => {
        const lobby = room();
        expect(lobby.pawnOf({ pawn: '/chat-assets/pawns/gold.svg' })).toBe('/chat-assets/pawns/gold.svg');
        expect(lobby.pawnOf({ pawn: 'https://evil.test/x.svg' })).toBeNull();
        expect(lobby.pawnOf({ pawn: null })).toBeNull();
    });

    it('boot() keeps the announcement as plain text and ignores a non-string', () => {
        const lobby = chatLobby() as any;
        lobby.connect = () => {};
        lobby.startRoster = () => {};
        lobby.boot({ room: { id: 1 }, announcement: 'Maintenance tonight' });
        expect(lobby.announcement).toBe('Maintenance tonight');
        lobby.boot({ room: { id: 1 }, announcement: { x: 1 } });
        expect(lobby.announcement).toBeNull();
    });

    it('startRoster() does nothing for guests', () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        room(false).startRoster();
        expect(fetchMock).not.toHaveBeenCalled();
    });
});

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

    it('keeps --chat-background for hex and gradient only', () => {
        expect(safeStyle({ '--chat-background': '#112233' })).toEqual({ '--chat-background': '#112233' });
        expect(safeStyle({ '--chat-background': 'linear-gradient(45deg, #ff0000, #0000ff)' })).toEqual({
            '--chat-background': 'linear-gradient(45deg, #ff0000, #0000ff)',
        });
        expect(safeStyle({ '--chat-background': "url('/chat/backgrounds/x.webp')" })).toEqual({});
        expect(safeStyle({ '--chat-background': 'red;background:url(//evil)' })).toEqual({});
        expect(safeStyle({ '--chat-other': '#112233' })).toEqual({});
    });

    it('falls back to the body when a cached record has no parts', () => {
        expect(safeParts({ body: 'hi' })).toEqual([{ t: 'text', v: 'hi' }]);
    });

    it('only loads images from the pawn directory', () => {
        expect(safeImage({ t: 'img', v: '/chat-assets/pawns/default.svg' })).toBe('/chat-assets/pawns/default.svg');
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
        // happy-dom ships no window.confirm/prompt; give vi.spyOn something to wrap.
        window.confirm = () => false;
        window.prompt = () => null;
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 't';
        document.head.appendChild(meta);
    });

    afterEach(() => {
        vi.restoreAllMocks();
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
        const promptSpy = vi.spyOn(window, 'prompt').mockReturnValue(null);
        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
        await lobby.moderate(lobby.messages[0], 'ban');
        expect(promptSpy).toHaveBeenCalled();
        expect(fetchMock).not.toHaveBeenCalled();

        promptSpy.mockReturnValue('spam');
        confirmSpy.mockReturnValue(false);
        await lobby.moderate(lobby.messages[0], 'ban');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('test_ban_sends_a_timed_ban', async () => {
        const fetchMock = respond(200, { ok: true });
        const lobby = make();
        vi.spyOn(window, 'prompt').mockReturnValue('spam');
        const confirmMock = vi.spyOn(window, 'confirm').mockReturnValue(true);
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
        vi.spyOn(window, 'confirm').mockReturnValue(true);
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
        const confirmMock = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const lobby = make();
        await lobby.moderate(lobby.messages[0], 'delete');
        expect(confirmMock).toHaveBeenCalledTimes(1);
        expect(lobby.messages).toHaveLength(2);

        await lobby.moderate(lobby.messages[1], 'delete');
        expect(confirmMock).toHaveBeenCalledTimes(1);
        expect(lobby.messages.map((m: { id: number }) => m.id)).toEqual([1]);
    });

    it('test_delete_404_removes_the_line_without_an_error', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        respond(404, { message: 'Not found.' });
        const lobby = make();
        await lobby.moderate(lobby.messages[0], 'delete');
        expect(lobby.error).toBe('');
        expect(lobby.messages.map((m: { id: number }) => m.id)).toEqual([2]);
    });

    it('test_non_ok_json_null_body_shows_the_fallback_not_offline', async () => {
        respond(500, null);
        const lobby = make();
        await lobby.moderate(lobby.messages[0], 'gag');
        expect(lobby.error).toBe('That action failed.');
    });

    it('test_successful_moderation_shows_done_and_next_action_clears_it', async () => {
        respond(200, { ok: true });
        const lobby = make();
        await lobby.moderate(lobby.messages[0], 'gag');
        expect(lobby.notice).toBe('Done.');

        respond(403, { message: 'Nope.' });
        await lobby.moderate(lobby.messages[0], 'kick');
        expect(lobby.notice).toBe('');
        expect(lobby.error).toBe('Nope.');
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

describe('rich chat parts', () => {
    it('keeps only known flags and a strict hex colour', () => {
        const parts = safeParts({
            parts: [
                { t: 'text', v: 'a', b: true, evil: 'x', c: '#F00' },
                { t: 'text', v: 'b', c: 'red;background:url(x)' },
                { t: 'text', v: 'c', i: 'yes' },
            ],
        });

        expect(parts[0]).toEqual({ t: 'text', v: 'a', b: true, c: '#F00' });
        expect(parts[1]).toEqual({ t: 'text', v: 'b' });
        expect(parts[2]).toEqual({ t: 'text', v: 'c' });
    });

    it('only passes a vetted https image url', () => {
        const ok = 'https://i.example.test/a/cat.png?x=1';
        const parts = safeParts({
            parts: [
                { t: 'image', v: ok, sp: true },
                { t: 'image', v: 'http://i.example.test/a.png' },
                { t: 'image', v: 'javascript:alert(1)' },
                { t: 'image', v: 'https://i.example.test/a.png" onerror="x' },
                { t: 'image', v: 'https://i.example.test/a.svg' },
                { t: 'image', v: '/chat-uploads/' + 'a'.repeat(32) + '.webp' },
                { t: 'upload', v: ok },
            ],
        });

        expect(parts).toEqual([{ t: 'image', v: ok, sp: true }]);
        expect(safeEmbed(parts[0])).toBe(ok);
    });
});

describe('chat avatar', () => {
    it('passes only a plain https url', () => {
        expect(safeAvatar({ avatar: 'https://cdn.example.test/u/1.png' })).toBe('https://cdn.example.test/u/1.png');
        expect(safeAvatar({ avatar: 'http://cdn.example.test/u/1.png' })).toBeNull();
        expect(safeAvatar({ avatar: "https://cdn.example.test/a');background:url(x" })).toBeNull();
        expect(safeAvatar({ avatar: 'javascript:alert(1)' })).toBeNull();
        expect(safeAvatar({})).toBeNull();
    });
});

describe('mentions and replies', () => {
    it('splits @[Name] into a mention part and keeps formatting flags', () => {
        const parts = safeParts({ parts: [{ t: 'text', v: 'hi @[Rin Kun] there', b: true }] });

        expect(parts).toEqual([
            { t: 'text', v: 'hi ', b: true },
            { t: 'mention', v: '@Rin Kun', name: 'Rin Kun', b: true },
            { t: 'text', v: ' there', b: true },
        ]);
    });

    it('leaves text without a mention untouched', () => {
        expect(safeParts({ parts: [{ t: 'text', v: 'a @ b [x] @[' }] })).toEqual([{ t: 'text', v: 'a @ b [x] @[' }]);
    });

    it('keeps only plain strings in a reply preview', () => {
        expect(safeReply({ reply: { id: 3, name: 'Rin', text: 'hi', evil: '<b>' } })).toEqual({ id: 3, name: 'Rin', text: 'hi' });
        expect(safeReply({ reply: { id: '3' } })).toBeNull();
        expect(safeReply({})).toBeNull();
    });

    it('flattens a record to one line for the pinned bar', () => {
        expect(plainText({ parts: [{ t: 'text', v: 'look ' }, { t: 'image', v: 'https://i.example.test/a.png' }] })).toBe('look [รูป]');
    });
});
