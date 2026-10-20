/**
 * <theme-yt-player video-id="…" song="…">: an audio-only player for a
 * YouTube theme song on an anime page.
 *
 * Canonical copy: ahd-v2/resources/js/theme-yt-player.js (v1). neko-miku,
 * anime-hana, kurokami-v2 and lnw-anime carry byte-identical copies: change
 * it here, run tests/js/themeYtPlayer.test.ts, then copy the file over.
 *
 * Every card on a page shares one hidden YouTube player, created when the
 * first card scrolls near the viewport. Its iframe sits in a 1x1px,
 * transparent box (never display:none, which stops playback). The box only
 * opens when a phone refuses to start audio from our own button, so the
 * visitor can tap the video itself.
 *
 * Spec: ahd-admin/docs/superpowers/specs/2026-10-20-theme-song-players-design.md §4
 */

export const VIDEO_ID = /^[A-Za-z0-9_-]{11}$/;

const API_SRC = 'https://www.youtube.com/iframe_api';
const STATE = { ENDED: 0, PLAYING: 1, PAUSED: 2, BUFFERING: 3 };

const ICON = {
    play: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>',
    pause: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>',
    sound: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3A4.5 4.5 0 0 0 14 8v8a4.5 4.5 0 0 0 2.5-4z"/></svg>',
    muted: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9v6h4l5 5V4L7 9H3zm13.6 3 2.7-2.7-1.4-1.4-2.7 2.7-2.7-2.7-1.4 1.4 2.7 2.7-2.7 2.7 1.4 1.4 2.7-2.7 2.7 2.7 1.4-1.4z"/></svg>',
};

const BOX_CLOSED = {
    position: 'fixed', right: '0', bottom: '0', width: '1px', height: '1px',
    opacity: '0', pointerEvents: 'none', overflow: 'hidden', zIndex: '2147483000',
};

const BOX_OPEN = {
    position: 'fixed', right: '12px', bottom: '12px', width: '200px', height: 'auto',
    opacity: '1', pointerEvents: 'auto', overflow: 'visible', zIndex: '2147483000',
    background: '#000', color: '#fff', borderRadius: '8px', padding: '0 0 6px',
    boxShadow: '0 8px 24px rgba(0,0,0,.4)', font: '13px/1.4 system-ui, sans-serif',
};

const TEMPLATE = `
<style>
:host { display: block; color: var(--theme-player-fg, inherit); }
:host([data-state="unavailable"]) .controls { display: none; }
:host([data-state="unavailable"]) .unavailable { display: block; }
.card { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 12px;
    background: var(--theme-player-bg, rgba(127, 127, 127, .12)); }
.thumb { width: 48px; height: 48px; flex: none; border-radius: 8px; object-fit: cover; background: rgba(0, 0, 0, .2); }
.body { flex: 1; min-width: 0; }
.title { font-size: .875rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.controls { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
button { all: unset; box-sizing: border-box; cursor: pointer; flex: none; display: inline-flex;
    align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; }
button:focus-visible { outline: 2px solid var(--theme-player-accent, #e11d48); outline-offset: 2px; }
.play { background: var(--theme-player-accent, #e11d48); color: var(--theme-player-accent-fg, #fff); }
.mute { opacity: .8; }
svg { width: 18px; height: 18px; fill: currentColor; }
.seek { flex: 1; min-width: 0; accent-color: var(--theme-player-accent, #e11d48); }
.time { font-size: .75rem; opacity: .75; white-space: nowrap; font-variant-numeric: tabular-nums; }
.unavailable { display: none; margin-top: 4px; font-size: .8125rem; opacity: .85; }
.unavailable a { color: var(--theme-player-accent, #e11d48); }
</style>
<div class="card">
    <img class="thumb" alt="" width="48" height="48" loading="lazy">
    <div class="body">
        <div class="title"></div>
        <div class="controls">
            <button class="play" type="button"></button>
            <input class="seek" type="range" min="0" max="0" step="1" value="0" aria-label="ตำแหน่งเพลง" disabled>
            <span class="time">0:00 / --:--</span>
            <button class="mute" type="button"></button>
        </div>
        <div class="unavailable">เล่นไม่ได้ · <a target="_blank" rel="noopener">เปิดใน YouTube</a></div>
    </div>
</div>`;

function formatTime(seconds) {
    const total = Math.max(0, Math.floor(seconds || 0));
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

let apiPromise = null;

/** Load the YouTube IFrame API once per page; resolves with window.YT. */
export function loadYouTubeApi(timeoutMs = 10000) {
    if (apiPromise) return apiPromise;

    apiPromise = new Promise((resolve, reject) => {
        if (window.YT && typeof window.YT.Player === 'function') {
            resolve(window.YT);
            return;
        }

        // Chain, never replace: another script on the page may use the API too.
        const previous = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            if (typeof previous === 'function') previous();
            resolve(window.YT);
        };

        const script = document.createElement('script');
        script.src = API_SRC;
        script.async = true;
        script.onerror = () => reject(new Error('YouTube IFrame API failed to load'));
        document.head.appendChild(script);
        setTimeout(() => reject(new Error('YouTube IFrame API timed out')), timeoutMs);
    });

    return apiPromise;
}

/** The one hidden YouTube player behind every card on the page. */
export class Engine {
    constructor({ loadApi = loadYouTubeApi, gestureTimeoutMs = 3000, tickMs = 250 } = {}) {
        this.loadApi = loadApi;
        this.gestureTimeoutMs = gestureTimeoutMs;
        this.tickMs = tickMs;
        this.cards = new Set();
        this.player = null;
        this.starting = null;
        this.failed = false;
        this.current = null;
        this.ended = false;
        this.muted = false;
        this.box = null;
        this.ticker = null;
        this.gestureTimer = null;
    }

    register(card) {
        this.cards.add(card);
        card.setMuted(this.muted);
        if (this.failed) card.showUnavailable();
    }

    unregister(card) {
        this.cards.delete(card);
        if (this.current === card) {
            // An SPA navigation removes the card but not the player in <body>.
            this.pause(card);
            this.current = null;
        }
    }

    /** Create the shared player once. Rejects if YouTube is unreachable. */
    prepare() {
        if (this.player) return Promise.resolve(this.player);

        if (!this.starting) {
            this.starting = this.loadApi().then((YT) => new Promise((resolve) => {
                this.box = this.buildBox();
                const mount = document.createElement('div');
                this.box.prepend(mount);

                new YT.Player(mount, {
                    host: 'https://www.youtube-nocookie.com',
                    width: 200,
                    height: 113,
                    playerVars: {
                        playsinline: 1, controls: 0, disablekb: 1, rel: 0, iv_load_policy: 3,
                        origin: window.location.origin,
                    },
                    events: {
                        onReady: (event) => {
                            this.player = event.target;
                            if (this.muted) this.player.mute();
                            resolve(this.player);
                        },
                        onStateChange: (event) => this.onState(event.data),
                        onError: () => this.onError(),
                    },
                });
            }));

            this.starting.catch(() => {
                this.failed = true;
                this.cards.forEach((card) => card.showUnavailable());
            });
        }

        return this.starting;
    }

    play(card) {
        if (this.failed) {
            card.showUnavailable();
            return;
        }

        if (!this.player) {
            // Not ready yet. By the time it is, the tap no longer counts as a
            // gesture on most phones; the fallback below covers that.
            this.prepare().then(() => this.play(card), () => {});
            return;
        }

        // Compare cards, not video ids: the same song can be listed twice.
        const sameCard = this.current === card;
        if (this.current && !sameCard) this.current.setPlaying(false);
        this.current = card;

        if (!sameCard) {
            this.player.loadVideoById(card.videoId);
        } else {
            if (this.ended) this.player.seekTo(0, true);
            this.player.playVideo();
        }

        this.ended = false;
        card.setPlaying(true);
        this.armGestureFallback();
    }

    pause(card) {
        if (this.current !== card || !this.player) return;
        this.clearGestureTimer();
        this.player.pauseVideo();
        card.setPlaying(false);
    }

    seek(card, seconds) {
        if (this.current !== card || !this.player) return;
        this.ended = false;
        this.player.seekTo(seconds, true);
    }

    toggleMute() {
        this.muted = !this.muted;
        if (this.player) {
            if (this.muted) this.player.mute();
            else this.player.unMute();
        }
        this.cards.forEach((card) => card.setMuted(this.muted));
    }

    onState(state) {
        const card = this.current;
        if (!card) return;

        if (state === STATE.PLAYING || state === STATE.BUFFERING) {
            card.setPlaying(true);
            this.clearGestureTimer();
            this.closeBox();
            this.startTicker();
        } else if (state === STATE.PAUSED) {
            card.setPlaying(false);
            this.stopTicker();
            this.report();
        } else if (state === STATE.ENDED) {
            this.ended = true;
            card.setPlaying(false);
            this.stopTicker();
            card.setProgress(0, this.player.getDuration());
        }
    }

    onError() {
        const card = this.current;
        this.current = null;
        this.stopTicker();
        this.clearGestureTimer();
        this.closeBox();
        if (card) card.showUnavailable();
    }

    startTicker() {
        this.stopTicker();
        this.report();
        this.ticker = setInterval(() => this.report(), this.tickMs);
    }

    stopTicker() {
        if (this.ticker) clearInterval(this.ticker);
        this.ticker = null;
    }

    report() {
        if (this.current && this.player) {
            this.current.setProgress(this.player.getCurrentTime(), this.player.getDuration());
        }
    }

    armGestureFallback() {
        this.clearGestureTimer();
        this.gestureTimer = setTimeout(() => {
            this.gestureTimer = null;
            const state = this.player ? this.player.getPlayerState() : -1;
            if (state !== STATE.PLAYING && state !== STATE.BUFFERING) this.openBox();
        }, this.gestureTimeoutMs);
    }

    clearGestureTimer() {
        if (this.gestureTimer) clearTimeout(this.gestureTimer);
        this.gestureTimer = null;
    }

    buildBox() {
        const box = document.createElement('div');
        box.setAttribute('data-theme-yt-engine', '');

        const hint = document.createElement('div');
        hint.textContent = 'แตะที่วิดีโอเพื่อเล่น';
        hint.style.padding = '6px 8px 0';

        const close = document.createElement('button');
        close.type = 'button';
        close.textContent = 'ปิด';
        close.setAttribute('aria-label', 'ปิดวิดีโอ');
        Object.assign(close.style, { margin: '4px 8px 0', background: 'none', border: '0', color: 'inherit', cursor: 'pointer', textDecoration: 'underline' });
        close.addEventListener('click', () => {
            if (this.current) this.pause(this.current);
            this.closeBox();
        });

        box.append(hint, close);
        this.extras = [hint, close];
        document.body.appendChild(box);
        this.closeBox(box);
        return box;
    }

    openBox() {
        if (!this.box) return;
        Object.assign(this.box.style, BOX_OPEN);
        this.extras.forEach((el) => { el.style.display = ''; });
        this.box.setAttribute('data-open', 'true');
    }

    closeBox(box = this.box) {
        if (!box) return;
        Object.assign(box.style, BOX_CLOSED);
        box.style.background = '';
        box.style.padding = '';
        box.style.boxShadow = '';
        (this.extras || []).forEach((el) => { el.style.display = 'none'; });
        box.setAttribute('data-open', 'false');
    }
}

/** The card element class, bound to one engine. */
export function createPlayerElement(engine) {
    return class ThemeYtPlayer extends HTMLElement {
        connectedCallback() {
            const id = this.getAttribute('video-id') || '';
            if (!VIDEO_ID.test(id)) return;

            this.videoId = id;
            if (!this.shadowRoot) this.build();
            engine.register(this);
            this.observe();
        }

        disconnectedCallback() {
            if (!this.videoId) return;
            if (this.observer) this.observer.disconnect();
            this.observer = null;
            engine.unregister(this);
        }

        build() {
            const root = this.attachShadow({ mode: 'open' });
            root.innerHTML = TEMPLATE;
            this.el = (selector) => root.querySelector(selector);

            this.el('.title').textContent = this.getAttribute('song') || '';
            this.el('.thumb').setAttribute('src', `https://i.ytimg.com/vi/${this.videoId}/mqdefault.jpg`);
            this.el('.unavailable a').setAttribute('href', `https://www.youtube.com/watch?v=${this.videoId}`);
            this.setPlaying(false);

            this.el('.play').addEventListener('click', () => {
                if (this.playing) engine.pause(this);
                else engine.play(this);
            });

            const seek = this.el('.seek');
            seek.addEventListener('input', () => {
                this.seeking = true;
                this.showTime(Number(seek.value), Number(seek.max));
            });
            seek.addEventListener('change', () => {
                this.seeking = false;
                engine.seek(this, Number(seek.value));
            });

            this.el('.mute').addEventListener('click', () => engine.toggleMute());
        }

        observe() {
            const Observer = window.IntersectionObserver;
            // Without it the player is created on the first play instead.
            if (typeof Observer !== 'function' || this.observer) return;

            this.observer = new Observer((entries) => {
                if (!entries.some((entry) => entry.isIntersecting)) return;
                this.observer.disconnect();
                this.observer = null;
                engine.prepare().catch(() => {});
            }, { rootMargin: '300px' });
            this.observer.observe(this);
        }

        setPlaying(playing) {
            this.playing = playing;
            const button = this.el('.play');
            button.innerHTML = playing ? ICON.pause : ICON.play;
            button.setAttribute('aria-label', playing ? 'หยุดชั่วคราว' : 'เล่น');
        }

        setProgress(seconds, duration) {
            const seek = this.el('.seek');
            if (duration > 0) {
                seek.max = String(Math.floor(duration));
                seek.disabled = false;
            }
            if (!this.seeking) seek.value = String(Math.floor(seconds));
            this.showTime(seconds, duration);
        }

        showTime(seconds, duration) {
            this.el('.time').textContent = `${formatTime(seconds)} / ${duration > 0 ? formatTime(duration) : '--:--'}`;
            this.el('.seek').setAttribute('aria-valuetext', formatTime(seconds));
        }

        setMuted(muted) {
            const button = this.el('.mute');
            button.innerHTML = muted ? ICON.muted : ICON.sound;
            button.setAttribute('aria-label', muted ? 'เปิดเสียง' : 'ปิดเสียง');
        }

        showUnavailable() {
            this.playing = false;
            this.setAttribute('data-state', 'unavailable');
        }
    };
}

let defaultEngine = null;

/** Define the element once; a second call returns the existing class. */
export function defineThemeYtPlayer(name = 'theme-yt-player', engine) {
    const existing = customElements.get(name);
    if (existing) return existing;

    if (!engine) {
        if (!defaultEngine) defaultEngine = new Engine();
        engine = defaultEngine;
    }

    const element = createPlayerElement(engine);
    customElements.define(name, element);
    return element;
}

// Guarded so server-side rendering can import this module without a DOM.
if (typeof window !== 'undefined' && window.customElements) {
    defineThemeYtPlayer();
}
