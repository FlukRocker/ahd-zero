// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Engine, defineThemeYtPlayer } from '@/theme-yt-player.js';

const PLAYING = 1;
const ENDED = 0;

class FakePlayer {
    static created = 0;
    static last: FakePlayer;
    calls: Array<[string, ...unknown[]]> = [];
    state = -1;
    time = 0;
    duration = 0;
    opts: any;

    constructor(_el: HTMLElement, opts: any) {
        this.opts = opts;
        FakePlayer.created++;
        FakePlayer.last = this;
    }

    ready() {
        this.opts.events.onReady({ target: this });
    }

    emit(state: number) {
        this.state = state;
        this.opts.events.onStateChange({ data: state });
    }

    fail(code: number) {
        this.opts.events.onError({ data: code });
    }

    loadVideoById(id: string) { this.calls.push(['load', id]); }
    playVideo() { this.calls.push(['play']); }
    pauseVideo() { this.calls.push(['pause']); }
    seekTo(seconds: number) { this.calls.push(['seek', seconds]); }
    mute() { this.calls.push(['mute']); }
    unMute() { this.calls.push(['unMute']); }
    getPlayerState() { return this.state; }
    getCurrentTime() { return this.time; }
    getDuration() { return this.duration; }
}

let observers: Array<{ cb: (entries: any[]) => void; el?: Element }> = [];

class FakeIntersectionObserver {
    cb: (entries: any[]) => void;
    el?: Element;
    constructor(cb: (entries: any[]) => void) {
        this.cb = cb;
        observers.push(this);
    }
    observe(el: Element) { this.el = el; }
    disconnect() { observers = observers.filter((o) => o !== this); }
}

function scrollNear() {
    [...observers].forEach((o) => o.cb([{ isIntersecting: true, target: o.el }]));
}

async function flush() {
    for (let i = 0; i < 10; i++) await Promise.resolve();
}

let tagCount = 0;

function setup(options: Record<string, unknown> = {}) {
    const loadApi = vi.fn(() => Promise.resolve({ Player: FakePlayer }));
    const engine = new Engine({ loadApi, ...options });
    const tag = `test-yt-player-${++tagCount}`;
    defineThemeYtPlayer(tag, engine);

    const make = (id: string, song = 'Kaikai Kitan') => {
        const el = document.createElement(tag) as any;
        el.setAttribute('video-id', id);
        el.setAttribute('song', song);
        document.body.appendChild(el);
        return el;
    };

    return { engine, loadApi, make };
}

const $ = (el: any, selector: string) => el.shadowRoot.querySelector(selector);

beforeEach(() => {
    FakePlayer.created = 0;
    vi.stubGlobal('IntersectionObserver', FakeIntersectionObserver);
});

afterEach(() => {
    document.body.innerHTML = '';
    observers = [];
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('<theme-yt-player>', () => {
    it('renders the song with a YouTube thumbnail', () => {
        const { make } = setup();
        const card = make('1tk1pqwrOys', 'Kaikai Kitan · Eve');

        expect($(card, '.title').textContent).toBe('Kaikai Kitan · Eve');
        expect($(card, '.thumb').getAttribute('src')).toBe('https://i.ytimg.com/vi/1tk1pqwrOys/mqdefault.jpg');
        expect($(card, '.play').getAttribute('aria-label')).toBe('เล่น');
    });

    it('renders nothing for an invalid video id', () => {
        const { make, engine } = setup();
        const card = make('not-an-id');

        expect(card.shadowRoot).toBeNull();
        expect(engine.cards.size).toBe(0);
        expect(observers).toHaveLength(0);
    });

    it('does not load the YouTube API until a card nears the viewport', async () => {
        const { make, loadApi } = setup();
        make('1tk1pqwrOys');
        make('aaaaaaaaaaa');

        expect(loadApi).not.toHaveBeenCalled();

        scrollNear();
        await flush();

        expect(loadApi).toHaveBeenCalledTimes(1);
        expect(FakePlayer.created).toBe(1);
    });

    it('plays a card by loading its video into the one shared player', async () => {
        const { make } = setup();
        const a = make('1tk1pqwrOys');
        const b = make('bbbbbbbbbbb');
        scrollNear();
        await flush();
        FakePlayer.last.ready();

        $(a, '.play').click();
        expect(FakePlayer.last.calls).toContainEqual(['load', '1tk1pqwrOys']);
        FakePlayer.last.emit(PLAYING);
        expect($(a, '.play').getAttribute('aria-label')).toBe('หยุดชั่วคราว');

        $(b, '.play').click();
        expect(FakePlayer.last.calls).toContainEqual(['load', 'bbbbbbbbbbb']);
        expect($(a, '.play').getAttribute('aria-label')).toBe('เล่น');
        expect(FakePlayer.created).toBe(1);
    });

    it('two cards with the same video play independently', async () => {
        const { make } = setup();
        const opening = make('1tk1pqwrOys', 'OP');
        const ending = make('1tk1pqwrOys', 'ED');
        scrollNear();
        await flush();
        FakePlayer.last.ready();

        $(opening, '.play').click();
        FakePlayer.last.emit(PLAYING);
        $(ending, '.play').click();

        const loads = FakePlayer.last.calls.filter((c) => c[0] === 'load');
        expect(loads).toHaveLength(2);
        expect($(opening, '.play').getAttribute('aria-label')).toBe('เล่น');
    });

    it('pauses, seeks and mutes through the shared player', async () => {
        const { make } = setup();
        const a = make('1tk1pqwrOys');
        const b = make('bbbbbbbbbbb');
        scrollNear();
        await flush();
        FakePlayer.last.ready();

        $(a, '.play').click();
        FakePlayer.last.emit(PLAYING);
        $(a, '.play').click();
        expect(FakePlayer.last.calls).toContainEqual(['pause']);

        const seek = $(a, '.seek');
        seek.max = '200';
        seek.value = '72';
        seek.dispatchEvent(new Event('change'));
        expect(FakePlayer.last.calls).toContainEqual(['seek', 72]);

        $(a, '.mute').click();
        expect(FakePlayer.last.calls).toContainEqual(['mute']);
        expect($(b, '.mute').getAttribute('aria-label')).toBe('เปิดเสียง');
        $(b, '.mute').click();
        expect(FakePlayer.last.calls).toContainEqual(['unMute']);
    });

    it('shows position and duration while playing', async () => {
        vi.useFakeTimers();
        const { make } = setup();
        const a = make('1tk1pqwrOys');
        scrollNear();
        await flush();
        FakePlayer.last.ready();

        $(a, '.play').click();
        FakePlayer.last.duration = 221;
        FakePlayer.last.time = 72;
        FakePlayer.last.emit(PLAYING);
        vi.advanceTimersByTime(250);

        expect($(a, '.time').textContent).toBe('1:12 / 3:41');
        expect($(a, '.seek').disabled).toBe(false);
        expect($(a, '.seek').getAttribute('aria-valuetext')).toBe('1:12');
    });

    it('restarts a song that ended', async () => {
        const { make } = setup();
        const a = make('1tk1pqwrOys');
        scrollNear();
        await flush();
        FakePlayer.last.ready();

        $(a, '.play').click();
        FakePlayer.last.emit(PLAYING);
        FakePlayer.last.emit(ENDED);
        expect($(a, '.play').getAttribute('aria-label')).toBe('เล่น');

        $(a, '.play').click();
        expect(FakePlayer.last.calls.slice(-2)).toEqual([['seek', 0], ['play']]);
    });

    it('offers YouTube instead when a video cannot be embedded', async () => {
        const { make } = setup();
        const a = make('1tk1pqwrOys');
        scrollNear();
        await flush();
        FakePlayer.last.ready();

        $(a, '.play').click();
        FakePlayer.last.fail(150);

        expect(a.getAttribute('data-state')).toBe('unavailable');
        expect($(a, '.unavailable a').getAttribute('href')).toBe('https://www.youtube.com/watch?v=1tk1pqwrOys');
    });

    it('marks every card unavailable when the YouTube API cannot load', async () => {
        const loadApi = vi.fn(() => Promise.reject(new Error('blocked')));
        const engine = new Engine({ loadApi });
        const tag = `test-yt-player-${++tagCount}`;
        defineThemeYtPlayer(tag, engine);
        const a = document.createElement(tag);
        a.setAttribute('video-id', '1tk1pqwrOys');
        const b = document.createElement(tag);
        b.setAttribute('video-id', 'bbbbbbbbbbb');
        document.body.append(a, b);

        scrollNear();
        await flush();

        expect(a.getAttribute('data-state')).toBe('unavailable');
        expect(b.getAttribute('data-state')).toBe('unavailable');
    });

    it('reveals the video for a direct tap when play does not start within 3s', async () => {
        vi.useFakeTimers();
        const { make, engine } = setup();
        const a = make('1tk1pqwrOys');
        scrollNear();
        await flush();
        FakePlayer.last.ready();

        $(a, '.play').click();
        expect(engine.box!.getAttribute('data-open')).toBe('false');
        vi.advanceTimersByTime(3000);
        expect(engine.box!.getAttribute('data-open')).toBe('true');

        FakePlayer.last.emit(PLAYING);
        expect(engine.box!.getAttribute('data-open')).toBe('false');
    });

    it('does not reveal the video when the visitor paused within 3s', async () => {
        vi.useFakeTimers();
        const { make, engine } = setup();
        const a = make('1tk1pqwrOys');
        scrollNear();
        await flush();
        FakePlayer.last.ready();

        $(a, '.play').click();
        $(a, '.play').click();
        vi.advanceTimersByTime(3000);

        expect(engine.box!.getAttribute('data-open')).toBe('false');
    });

    it('pauses when the playing card leaves the page', async () => {
        const { make } = setup();
        const a = make('1tk1pqwrOys');
        scrollNear();
        await flush();
        FakePlayer.last.ready();

        $(a, '.play').click();
        FakePlayer.last.emit(PLAYING);
        a.remove();

        expect(FakePlayer.last.calls.at(-1)).toEqual(['pause']);
    });

    it('defining the element twice is harmless', () => {
        const first = defineThemeYtPlayer('test-yt-player-twice', new Engine());
        const second = defineThemeYtPlayer('test-yt-player-twice', new Engine());

        expect(second).toBe(first);
    });

    it('registers <theme-yt-player> on import', () => {
        expect(customElements.get('theme-yt-player')).toBeDefined();
    });
});
