// Superadmin pages only (the layout sets data-heartbeat-url on <body> for that role): a small ping every five
// minutes while the tab is visible keeps the session from lapsing unseen. A 401 or 419 means it already did, so the
// browser goes to the superadmin sign-in with a plain message instead of showing an error page.
const url = document.body?.dataset.heartbeatUrl;
const loginUrl = document.body?.dataset.heartbeatLogin;

if (url && loginUrl) {
    const EVERY = 5 * 60 * 1000;
    let timer = null;

    const ping = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (response.status === 401 || response.status === 419) {
                stop();
                window.location.assign(loginUrl + (loginUrl.includes('?') ? '&' : '?') + 'sesi=berakhir');
            }
        } catch {
            // Offline for a moment: try again at the next tick.
        }
    };

    const start = () => {
        if (timer === null) timer = setInterval(ping, EVERY);
    };
    const stop = () => {
        clearInterval(timer);
        timer = null;
    };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop();
        else {
            ping();
            start();
        }
    });

    if (!document.hidden) start();
}
