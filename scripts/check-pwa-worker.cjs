// Standalone service-worker security smoke test; runs with Node (no dependencies).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const handlers = new Map();
const stored = new Map();
const location = {origin: 'https://grovemap.onrender.com'};
let networkOnline = true;
const calls = [];
const response = (type = 'text/html') => ({
    ok: true, type: 'basic',
    headers: {
        has: () => false,
        get: (name) => name.toLowerCase() === 'content-type' ? type : null,
    },
    clone() { return this; },
});
const offlinePage = response();
const cache = {
    async addAll(paths) { for (const p of paths) stored.set(p, response()); },
    async match(arg) { return stored.get(typeof arg === 'string' ? arg : arg.url) || (arg === '/offline.html' ? offlinePage : undefined); },
    async put(request, value) { stored.set(request.url, value); },
};
const ctx = {
    self: {
        location,
        addEventListener: (type, handler) => handlers.set(type, handler),
        skipWaiting: async () => {},
        clients: {claim: async () => {}},
    },
    caches: {
        open: async () => cache,
        keys: async () => ['grovemap-public-v1'],
        delete: async () => true,
    },
    fetch: async (req) => {
        calls.push(req.url);
        if (!networkOnline) throw new Error('Offline');
        return response(req.url.endsWith('.css') ? 'text/css' : 'text/html');
    },
    Response: {error: () => new Error('offline fallback missing')},
    URL,
    Promise,
};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/sw.js'), 'utf8'), ctx);
assert(handlers.has('install') && handlers.has('activate') && handlers.has('fetch'));

async function handle(url, mode) {
    const req = {url: location.origin + url, mode, method: 'GET'};
    let promise;
    handlers.get('fetch')({request: req, respondWith: (p) => {promise = Promise.resolve(p);}});
    return promise ? await promise : undefined;
}
(async () => {
    await new Promise((resolve, reject) => handlers.get('install')({waitUntil: (p) => p.then(resolve, reject)}));

    // A private page must NEVER be added to the Cache API, even when visited online.
    await handle('/reservations/4/cts', 'navigate');
    assert(!Array.from(stored.keys()).some((key) => key.includes('reservations')));
    await handle('/dashboard', 'navigate');
    assert(!Array.from(stored.keys()).some((key) => key.includes('dashboard')));

    // A public compiled file may be cached; a private PDF may not.
    await handle('/build/assets/app-a1b2c3.css', 'no-cors');
    assert(stored.has(location.origin + '/build/assets/app-a1b2c3.css'));
    const privatePdf = await handle('/storage/cts/private-file.pdf', 'no-cors');
    assert.equal(privatePdf, undefined);
    assert(!Array.from(stored.keys()).some((key) => key.includes('.pdf')));

    networkOnline = false;
    const fallback = await handle('/reservations/4/cts', 'navigate');
    assert.equal(fallback, stored.get('/offline.html'));

    console.log('PWA worker smoke checks passed: no private-page or CTS caching; public static caching and offline fallback work.');
})().catch((e) => {console.error(e);process.exitCode = 1;});
