// Sends the JavaScript errors of the page to the server: they appear on the admin's Erreurs
// page (source « Navigateur »), so a bug seen only on someone's phone doesn't go unnoticed.
// Only for logged-in visitors: the page then carries the address (meta error-report-url).

const MAX_PER_PAGE = 10;
const sent = new Set();

// Also called by the code itself for failures that aren't exceptions (player_controller.js).
export function reportError(message, details = {})
{
    const url = document.querySelector('meta[name="error-report-url"]')?.content;

    // Logged out, the same error again, or a page stuck in a loop: nothing to send.
    if (!url || !message || sent.has(message) || sent.size >= MAX_PER_PAGE) {
        return;
    }

    sent.add(message);

    // JSON: a form from another site can't send it (the server refuses anything else).
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: String(message).slice(0, 500),
            stack: details.stack ? String(details.stack).slice(0, 2000) : null,
            page: window.location.pathname,
        }),
        keepalive: true,
    }).catch(() => {});
}

window.addEventListener('error', event => {
    // Errors of browser extensions, or "Script error." from another site's script: not ours.
    if (!event.filename || new URL(event.filename, window.location.href).origin !== window.location.origin) {
        return;
    }

    reportError(event.message, {
        stack: event.error?.stack ?? `${event.filename}:${event.lineno}:${event.colno}`,
    });
});

window.addEventListener('unhandledrejection', event => {
    const reason = event.reason;

    // A request cancelled on purpose (page left, download stopped) isn't a failure.
    if (reason?.name === 'AbortError') {
        return;
    }

    reportError(reason?.message ?? String(reason), { stack: reason?.stack });
});
