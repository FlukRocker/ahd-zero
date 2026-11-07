/**
 * The chat lobby.
 *
 * Two rules hold the security story together:
 *  1. Nothing here writes markup into the DOM. Alpine's x-text creates text nodes, so a
 *     message body is never parsed as markup.
 *  2. Echo and pusher-js are imported dynamically, so the ~40 KB socket client
 *     is fetched on /chat only and not on every SEO page.
 */
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
                .listen('.member.sanctioned', ({ uuid, action }) => {
                    // Cosmetic only — the server already refuses their
                    // posts and their next subscribe. Leaving the roster on
                    // our own say-so just stops a banned name sitting in
                    // the online list until the socket drops.
                    if (uuid === this.config.me && (action === 'ban' || action === 'kick')) {
                        echo.leave('chat.roster');
                        this.roster = this.roster.filter((p) => p.uuid !== uuid);
                    }
                });

            if (this.config.canPost) {
                echo.join('chat.roster')
                    .here((people) => { this.roster = people; })
                    .joining((person) => { this.roster.push(person); })
                    .leaving((person) => {
                        this.roster = this.roster.filter((p) => p.uuid !== person.uuid);
                    });
            }
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
            if (this.messages.some((existing) => existing.id === line.id)) {
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
            } catch {
                this.error = 'You appear to be offline.';
            } finally {
                this.sending = false;
            }
        },
    };
}
