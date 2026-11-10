/**
 * Tells the server this member is on the site, so the chat's online list
 * covers every page and not only the ones with a chat box open. The layout
 * emits <meta name="ahd-presence"> for logged-in members only; guests send
 * nothing. Pings only while the tab is visible.
 */
const INTERVAL_MS = 45_000;

export default function startPresence() {
    if (!document.querySelector('meta[name="ahd-presence"]')) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    let timer = null;

    const ping = async () => {
        try {
            const response = await fetch('/chat/presence', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                credentials: 'same-origin',
                keepalive: true,
            });

            // Logged out or session expired: stop quietly.
            if (response.status === 401 || response.status === 419) {
                stop();
            }
        } catch {
            /* offline: the next tick tries again */
        }
    };

    const stop = () => {
        clearInterval(timer);
        timer = null;
    };

    const start = () => {
        if (timer === null && document.visibilityState === 'visible') {
            ping();
            timer = setInterval(ping, INTERVAL_MS);
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            start();
        } else {
            stop();
        }
    });

    start();
}
