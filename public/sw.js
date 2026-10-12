/* GroveMap PWA: public assets only. Authenticated routes and requests remain network-only. */
const CACHE_NAME = 'grovemap-public-v1';
const CACHE_PREFIX = 'grovemap-public-';
const PRECACHE = [
    '/offline.html',
    '/manifest.json',
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
    '/pwa/icon-maskable-512.png',
    '/pwa/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

function isPublicStaticAsset(url) {
    // Only hashed, compiler-generated assets; never cache /storage, /api,
    // /reservations, /cts, /inquiries, /account, or any HTML response.
    return /^\/build\/assets\/[A-Za-z0-9_.-]+\.(?:css|js|woff2?|png|webp|svg)$/.test(url.pathname)
        || PRECACHE.includes(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        // Never cache a Laravel page, session, cookie, CSRF token, or form response.
        event.respondWith(
            fetch(request).catch(async () => {
                const cache = await caches.open(CACHE_NAME);
                return await cache.match('/offline.html')
                    || Response.error();
            }),
        );
        return;
    }

    if (!isPublicStaticAsset(url)) return; // Network-only for all other traffic.

    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME);
        const cached = await cache.match(request);
        if (cached) return cached;

        const response = await fetch(request);
        const cacheControl = response.headers.get('cache-control') || '';
        if (response.ok && response.type === 'basic'
            && !response.headers.has('set-cookie')
            && !/private|no-store/i.test(cacheControl)
            && !response.headers.get('content-type')?.includes('text/html')) {
            await cache.put(request, response.clone());
        }
        return response;
    })());
});
