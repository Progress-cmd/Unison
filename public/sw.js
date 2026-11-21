// The first Unison (v1) installed a service worker at this address, and browsers that used it
// still run it: it stands between the page and the network (Firefox playback could stall behind
// it) and serves the v1 offline cache. Browsers check this file for updates; this version takes
// the old one's place, empties its caches and uninstalls itself. The page isn't reloaded, so the
// music playing doesn't stop. To keep as long as a browser may still have the v1 one.
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const names = await caches.keys();

        await Promise.all(names.map(name => caches.delete(name)));

        // Takes the open pages from the old worker (this one intercepts nothing), then leaves.
        await self.clients.claim();
        await self.registration.unregister();
    })());
});
