const ATTENDANCE_CACHE = 'attendance-shell-v2';
const ATTENDANCE_ASSETS = [
    '/js/attendance-offline-sync.js',
    '/js/attendance-pin.js',
];

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(ATTENDANCE_CACHE);

        for (const url of ['/attendance', ...ATTENDANCE_ASSETS]) {
            try {
                const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                if (response.ok) await cache.put(url, response.clone());
            } catch (error) {
                // A failed pre-cache must not be stored as an application response.
            }
        }

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names
            .filter(name => name.startsWith('attendance-shell-') && name !== ATTENDANCE_CACHE)
            .map(name => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', event => {
    if (event.data?.type !== 'cache-attendance-page' || !event.source?.url) return;

    const sourceUrl = new URL(event.source.url);
    let pageUrl;
    try {
        pageUrl = new URL(event.data.url);
    } catch (error) {
        return;
    }

    if (
        sourceUrl.origin !== self.location.origin
        || pageUrl.origin !== self.location.origin
        || !/^\/attendance\/\d+$/.test(pageUrl.pathname)
        || pageUrl.pathname !== sourceUrl.pathname
    ) return;

    event.waitUntil((async () => {
        try {
            const response = await fetch(pageUrl.href, { credentials: 'same-origin', cache: 'no-store' });
            if (
                response.ok
                && response.headers.get('X-Attendance-Offline-Cache') === 'public-attendance'
            ) {
                const cache = await caches.open(ATTENDANCE_CACHE);
                await cache.put(pageUrl.href, response.clone());
            }
        } catch (error) {
            // Keep the existing cached response; never cache a failed request.
        }
    })());
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    const isAttendancePage = request.mode === 'navigate'
        && (url.pathname === '/attendance' || /^\/attendance\/\d+$/.test(url.pathname));
    const isAttendanceAsset = ATTENDANCE_ASSETS.includes(url.pathname);

    if (isAttendancePage) {
        event.respondWith((async () => {
            const cache = await caches.open(ATTENDANCE_CACHE);

            try {
                const response = await fetch(request);
                const canCachePinPage = url.pathname === '/attendance' && response.ok;
                const isCleanAuthorizedForm = response.ok
                    && response.headers.get('X-Attendance-Offline-Cache') === 'public-attendance';

                if (canCachePinPage || isCleanAuthorizedForm) {
                    await cache.put(request, response.clone());
                }

                return response;
            } catch (error) {
                const cached = await cache.match(request);
                if (cached) return cached;

                const cachedPin = await cache.match('/attendance');
                if (cachedPin && url.pathname !== '/attendance') return cachedPin;

                return new Response(
                    '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Attendance unavailable offline</title><body><main><h1>Attendance page not saved on this device</h1><p>Reconnect and open this event attendance page once before using it offline.</p><a href="/attendance">Back to Attendance</a></main></body></html>',
                    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                );
            }
        })());
        return;
    }

    if (isAttendanceAsset) {
        event.respondWith((async () => {
            const cache = await caches.open(ATTENDANCE_CACHE);
            try {
                const response = await fetch(request);
                if (response.ok) await cache.put(request, response.clone());
                return response;
            } catch (error) {
                return (await cache.match(request)) || Response.error();
            }
        })());
    }
});
