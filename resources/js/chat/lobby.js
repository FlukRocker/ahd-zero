/**
 * The chat lobby.
 *
 * Two rules hold the security story together:
 *  1. Nothing here writes markup into the DOM. Alpine's x-text creates text nodes, so a
 *     message body is never parsed as markup.
 *  2. Echo and pusher-js are imported dynamically, so the ~40 KB socket client
 *     is fetched on /chat only and not on every SEO page.
 */
import { allowedActions } from './permissions';

// The only custom properties a chat line may set, and the only image path it
// may load from. The server (CosmeticStyle) already validated the values; this
// is a second, independent gate so a malformed cached record cannot reach CSS.
export const STYLE_KEYS = ['--chat-name-color', '--chat-name-gradient', '--chat-glow'];
const SAFE_VALUE = /^[#a-z0-9 .,()%-]+$/i;
const SAFE_IMAGE = /^\/chat\/pawns\/[a-z0-9-]{1,32}\.svg$/;

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

    return line.parts.filter((part) => part && (part.t === 'text' || part.t === 'img'));
}

export function safeImage(part) {
    return part.t === 'img' && typeof part.v === 'string' && SAFE_IMAGE.test(part.v) ? part.v : null;
}

export default function chatLobby() {
    return {
        messages: [],
        roster: [],
        draft: '',
        sending: false,
        error: '',
        notice: '',
        busy: {},
        menuOpen: null,
        config: null,

        start() {
            const node = document.getElementById('chat-bootstrap');
            this.config = JSON.parse(node.textContent);
            this.messages = this.config.history ?? [];
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

            echo.channel('chat.lobby')
                .listen('.message.sent', (line) => this.append(line))
                .listen('.message.deleted', ({ id }) => {
                    this.messages = this.messages.filter((line) => line.id !== id);
                })
                .listen('.member.sanctioned', (event) => this.onSanctioned(event, echo));

            if (this.config.canPost) {
                echo.join('chat.roster')
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
                echo.leave('chat.roster');
                this.roster = this.roster.filter((p) => p.uuid !== uuid);
            }
        },

        actionsFor(line) {
            return allowedActions(this.config?.myRank ?? null, this.config?.me ?? null, !!this.config?.canPost, line);
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

        imageSrc(part) {
            return safeImage(part);
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

            try {
                const response = await fetch('/chat/messages', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ body }),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    this.error = payload.message
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
                    const payload = await response.json().catch(() => ({}));
                    this.error = payload.message
                        ?? (response.status === 429 ? 'Slow down a moment.' : 'That action failed.');

                    return;
                }

                if (action === 'delete') {
                    this.messages = this.messages.filter((m) => m.id !== line.id);
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
