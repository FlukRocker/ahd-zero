/**
 * The chat lobby.
 *
 * Two rules hold the security story together:
 *  1. Nothing here writes markup into the DOM. Alpine's x-text creates text nodes, so a
 *     message body is never parsed as markup.
 *  2. Echo and pusher-js are imported dynamically, so the ~40 KB socket client
 *     is fetched on /chat only and not on every SEO page.
 */
import { allowedActions, canReport } from './permissions';

// The only custom properties a chat line may set, and the only image path it
// may load from. The server (CosmeticStyle) already validated the values; this
// is a second, independent gate so a malformed cached record cannot reach CSS.
export const STYLE_KEYS = ['--chat-name-color', '--chat-name-gradient', '--chat-glow', '--chat-background'];
const SAFE_VALUE = /^[#a-z0-9 .,()%-]+$/i;
const SAFE_IMAGE = /^\/chat-assets\/pawns\/[a-z0-9-]{1,32}\.svg$/;
// A member-embedded picture: https, no quote/space/bracket/angle/backslash, an
// image extension. The server vetted it (Bbcode::imageUrl); this is the second gate.
const SAFE_EMBED = /^https:\/\/[^\s[\]"'<>\\]{4,400}\.(?:jpe?g|png|gif|webp)(?:[?#][^\s[\]"'<>\\]*)?$/i;
const SAFE_COLOR = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;
const FLAGS = ['b', 'i', 'u', 's', 'sp'];

export function safeStyle(style) {
    const out = {};

    if (style === null || typeof style !== 'object') {
        return out;
    }

    for (const key of STYLE_KEYS) {
        const value = style[key];

        if (typeof value === 'string' && SAFE_VALUE.test(value)) {
            out[key] = value;
        }
    }

    return out;
}

export function safeParts(line) {
    if (!Array.isArray(line.parts)) {
        return [{ t: 'text', v: String(line.body ?? '') }];
    }

    const out = [];

    for (const part of line.parts) {
        if (!part || typeof part !== 'object') {
            continue;
        }

        if (part.t === 'img') {
            out.push({ t: 'img', v: part.v });
        } else if (part.t === 'image') {
            // Only a vetted https image URL survives; anything else is dropped.
            if (typeof part.v === 'string' && SAFE_EMBED.test(part.v)) {
                out.push(part.sp === true ? { t: 'image', v: part.v, sp: true } : { t: 'image', v: part.v });
            }
        } else if (part.t === 'text') {
            // Copy known flags only; a missing flag (old cached record) is false.
            const clean = { t: 'text', v: String(part.v ?? '') };

            for (const flag of FLAGS) {
                if (part[flag] === true) {
                    clean[flag] = true;
                }
            }

            if (typeof part.c === 'string' && SAFE_COLOR.test(part.c)) {
                clean.c = part.c;
            }

            out.push(clean);
        }
    }

    return out;
}

export function safeImage(part) {
    return part.t === 'img' && typeof part.v === 'string' && SAFE_IMAGE.test(part.v) ? part.v : null;
}

export function safeEmbed(part) {
    return part.t === 'image' && typeof part.v === 'string' && SAFE_EMBED.test(part.v) ? part.v : null;
}

export default function chatLobby() {
    return {
        messages: [],
        roster: [],
        draft: '',
        spoiled: {},
        sending: false,
        error: '',
        notice: '',
        busy: {},
        menuOpen: null,
        config: null,
        // Report modal state (one shared modal in the page).
        reportLine: null,
        reportReason: 'spam',
        reportNote: '',
        reportMessage: '',
        reportBusy: false,

        start() {
            const node = document.getElementById('chat-bootstrap');
            this.boot(JSON.parse(node.textContent));
        },

        boot(config) {
            this.config = config;
            this.messages = config.history ?? [];
            this.connect();
        },

        async connect() {
            // Vite inlines these at build time. They must NOT come from the
            // server-rendered bootstrap: `artisan optimize` caches config,
            // and env() is null after that, so the host would silently
            // disappear in production while every test stayed green.
            const reverb = {
                key: import.meta.env.VITE_REVERB_APP_KEY,
                host: import.meta.env.VITE_REVERB_HOST,
                port: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
                scheme: import.meta.env.VITE_REVERB_SCHEME ?? 'https',
            };

            if (!reverb.key || !reverb.host) {
                return; // No socket configured: the page still works over HTTP.
            }

            const [{ default: Echo }, { default: Pusher }] = await Promise.all([
                import('laravel-echo'),
                import('pusher-js'),
            ]);

            window.Pusher = Pusher;

            const echo = new Echo({
                broadcaster: 'reverb',
                key: reverb.key,
                wsHost: reverb.host,
                wsPort: reverb.port,
                wssPort: reverb.port,
                forceTLS: reverb.scheme === 'https',
                enabledTransports: ['ws', 'wss'],
            });

            const roomId = this.config.room.id;

            echo.channel(`chat.room.${roomId}`)
                .listen('.message.sent', (line) => this.append(line))
                .listen('.message.deleted', ({ id }) => {
                    this.messages = this.messages.filter((line) => line.id !== id);
                })
                .listen('.member.sanctioned', (event) => this.onSanctioned(event, echo));

            if (this.config.canPost) {
                echo.join(`chat.roster.${roomId}`)
                    .here((people) => { this.roster = people; })
                    .joining((person) => { this.roster.push(person); })
                    .leaving((person) => {
                        this.roster = this.roster.filter((p) => p.uuid !== person.uuid);
                    });
            }
        },

        onSanctioned({ uuid, action }, echo) {
            if (uuid !== this.config.me) {
                return;
            }

            if (action === 'gag') {
                this.notice = 'A moderator gagged you for a while.';
            } else if (action === 'ungag') {
                this.notice = '';
            } else if (action === 'ban' || action === 'kick') {
                // Cosmetic only: the server already refuses their posts and
                // their next subscribe. Leaving the roster on our own say-so
                // just stops a banned name sitting in the online list until
                // the socket drops.
                echo.leave('chat.roster.' + this.config.room.id);
                this.roster = this.roster.filter((p) => p.uuid !== uuid);
            }
        },

        actionsFor(line) {
            const actions = allowedActions(this.config?.myRank ?? null, this.config?.me ?? null, !!this.config?.canPost, line);

            if (canReport(this.config?.me ?? null, !!this.config?.canPost, line)) {
                actions.push('report');
            }

            return actions;
        },

        openReport(line) {
            this.menuOpen = null;
            this.reportLine = line;
            this.reportReason = 'spam';
            this.reportNote = '';
            this.reportMessage = '';
        },

        closeReport() {
            this.reportLine = null;
        },

        async sendReport() {
            if (this.reportBusy || this.reportLine === null) {
                return;
            }

            this.reportBusy = true;

            try {
                const body = {
                    target_type: 'chat_message',
                    target_id: String(this.reportLine.id),
                    reason: this.reportReason,
                };

                if (this.reportNote.trim() !== '') {
                    body.note = this.reportNote.trim();
                }

                const response = await fetch('/reports', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });

                if (response.status === 201) {
                    this.reportMessage = 'ขอบคุณ เราจะตรวจสอบ';
                } else if (response.status === 200) {
                    this.reportMessage = 'คุณรายงานรายการนี้แล้ว';
                } else {
                    const payload = await response.json().catch(() => ({}));
                    this.reportMessage = payload.message ?? 'ส่งรายงานไม่สำเร็จ';
                }
            } catch {
                this.reportMessage = 'ส่งรายงานไม่สำเร็จ';
            } finally {
                this.reportBusy = false;
            }
        },

        isLast(line) {
            const last = this.messages[this.messages.length - 1];

            return !!last && last.id === line.id;
        },

        toggleMenu(line) {
            this.menuOpen = this.menuOpen === line.id ? null : line.id;
        },

        /** An object of validated custom properties, applied via style.setProperty. */
        styleOf(line) {
            return safeStyle(line.style);
        },

        hasGradient(line) {
            return '--chat-name-gradient' in safeStyle(line.style);
        },

        partsOf(line) {
            return safeParts(line);
        },

        /** HH:MM from the ISO timestamp; empty when absent or unparsable. */
        timeOf(line) {
            const date = new Date(line.at);

            return Number.isNaN(date.getTime())
                ? ''
                : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        rankLabel(line) {
            return { main_owner: 'Owner', owner: 'Admin', mod: 'Mod' }[line.rank] ?? '';
        },

        imageSrc(part) {
            return safeImage(part);
        },

        embedSrc(part) {
            return safeEmbed(part);
        },

        /** Inline style for a text segment: only a validated hex colour. */
        textStyle(part) {
            return part.c ? { color: part.c } : {};
        },

        /** Wrap the draft selection in a BBCode tag (the toolbar buttons). */
        wrap(open, close) {
            const input = this.$refs.draftInput;
            const start = input?.selectionStart ?? this.draft.length;
            const end = input?.selectionEnd ?? this.draft.length;

            this.draft = this.draft.slice(0, start) + open + this.draft.slice(start, end) + close + this.draft.slice(end);
            this.$nextTick(() => input?.focus());
        },

        append(line) {
            // History and the live socket overlap on reconnect; a duplicate id
            // would also be a duplicate x-for :key.
            if (this.messages.some((existing) => String(existing.id) === String(line.id))) {
                return;
            }

            this.messages.push(line);

            if (this.messages.length > 200) {
                this.messages.shift();
            }

            this.$nextTick(() => {
                this.$refs.list.scrollTop = this.$refs.list.scrollHeight;
            });
        },

        async send() {
            const body = this.draft.trim();

            if (body === '' || this.sending) {
                return;
            }

            this.sending = true;
            this.error = '';
            this.notice = '';

            try {
                const headers = {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                };
                const payload = JSON.stringify({ body, room_id: this.config.room.id });

                const response = await fetch('/chat/messages', { method: 'POST', headers, body: payload });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                    this.error = firstError ?? payload.message
                        ?? (response.status === 429 ? 'Slow down a moment.' : 'That message was not sent.');

                    return;
                }

                this.draft = '';

                // Show the sender's own line now; if the socket is down it is the only copy they get,
                // and when the socket echo arrives append() skips it by id.
                const record = await response.json().catch(() => null);

                if (record !== null && typeof record === 'object' && 'id' in record) {
                    this.append(record);
                }
            } catch {
                this.error = 'You appear to be offline.';
            } finally {
                this.sending = false;
            }
        },

        async moderate(line, action) {
            const key = String(line.id) + action;

            if (this.busy[key]) {
                return;
            }

            if (action === 'report') {
                this.openReport(line);

                return;
            }

            const own = line.uuid === this.config?.me;
            let request;

            if (action === 'delete') {
                if (!own && !window.confirm('Delete this message?')) {
                    return;
                }

                request = { url: '/chat/messages/' + encodeURIComponent(line.id), method: 'DELETE', body: null };
            } else {
                const body = { action, target_uuid: line.uuid };

                if (action === 'gag') {
                    body.minutes = 10;
                } else if (action === 'ban') {
                    const reason = window.prompt('Reason for the ban (optional):', '');

                    if (reason === null || !window.confirm('Ban this member for 24 hours?')) {
                        return;
                    }

                    body.reason = reason;
                    body.minutes = 1440;
                }

                request = { url: '/chat/moderate', method: 'POST', body: JSON.stringify(body) };
            }

            this.busy = { ...this.busy, [key]: true };
            this.error = '';
            this.notice = '';
            this.menuOpen = null;

            try {
                const response = await fetch(request.url, {
                    method: request.method,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    credentials: 'same-origin',
                    body: request.body,
                });

                if (!response.ok) {
                    if (action === 'delete' && response.status === 404) {
                        // Already deleted elsewhere: drop the line, no error text.
                        this.messages = this.messages.filter((m) => m.id !== line.id);

                        return;
                    }

                    const payload = (await response.json().catch(() => ({}))) ?? {};
                    this.error = payload.message
                        ?? (response.status === 429 ? 'Slow down a moment.' : 'That action failed.');

                    return;
                }

                if (action === 'delete') {
                    this.messages = this.messages.filter((m) => m.id !== line.id);
                } else {
                    this.notice = 'Done.';
                }
            } catch {
                this.error = 'You appear to be offline.';
            } finally {
                const rest = { ...this.busy };
                delete rest[key];
                this.busy = rest;
            }
        },
    };
}

/**
 * The floating mini chat. Same behaviour as the lobby, but nothing is fetched
 * and no socket opens until the panel is first opened, so the bubble costs
 * nothing on shared-cached pages.
 */
export function chatMini() {
    return {
        ...chatLobby(),
        open: false,
        loading: false,
        loadError: false,

        async toggle() {
            this.open = !this.open;

            if (!this.open) {
                return;
            }

            if (this.config === null) {
                await this.load();
            }

            this.$nextTick(() => this.scrollDown());
        },

        async load() {
            if (this.loading) {
                return;
            }

            this.loading = true;
            this.loadError = false;

            try {
                const response = await fetch('/chat/mini', {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('mini chat load failed');
                }

                this.boot(await response.json());
            } catch {
                this.loadError = true;
            } finally {
                this.loading = false;
            }
        },

        scrollDown() {
            const list = this.$refs.list;

            if (list) {
                list.scrollTop = list.scrollHeight;
            }
        },
    };
}
