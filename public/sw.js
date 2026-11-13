const CACHE_NAME = 'lartisan-pwa-v1';
const OFFLINE_URL = '/offline';

const PRECACHE_URLS = [
    '/offline',
    '/manifest.webmanifest',
    '/favicon.svg',
    '/apple-touch-icon.png',
    '/pwa-192x192.png',
    '/pwa-512x512.png',
    '/pwa-maskable-512x512.png',
];

const STATIC_ASSET_PREFIXES = [
    '/build/',
    '/fonts/',
    '/images/',
    '/css/',
    '/js/',
    '/favicon',
    '/apple-touch-icon',
    '/pwa-',
    '/manifest.webmanifest',
];

const offlineFallbackResponse = () =>
    new Response(
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline - Lartisan</title></head><body style="font-family:sans-serif;padding:2rem;background:#f8fafc;color:#111827;"><h1>You are offline</h1><p>Check your internet connection and reload. Any saved field-visit or artisan drafts stay on this device.</p></body></html>',
        {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        },
    );

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) =>
                Promise.allSettled(
                    PRECACHE_URLS.map((url) => cache.add(url)),
                ),
            )
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((cacheNames) =>
                Promise.all(
                    cacheNames
                        .filter((cacheName) => cacheName !== CACHE_NAME)
                        .map((cacheName) => caches.delete(cacheName)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (
        url.pathname.startsWith('/webhooks/') ||
        url.pathname.startsWith('/livewire') ||
        event.request.headers.has('X-Inertia')
    ) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(async () => {
                const cachedOffline = await caches.match(OFFLINE_URL);

                return cachedOffline || offlineFallbackResponse();
            }),
        );

        return;
    }

    const isStaticAsset = STATIC_ASSET_PREFIXES.some((prefix) =>
        url.pathname.startsWith(prefix),
    );

    if (!isStaticAsset) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            const networkFetch = fetch(event.request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.ok) {
                        const responseToCache = networkResponse.clone();

                        caches
                            .open(CACHE_NAME)
                            .then((cache) =>
                                cache.put(event.request, responseToCache),
                            );
                    }

                    return networkResponse;
                })
                .catch(() => cachedResponse || offlineFallbackResponse());

            return cachedResponse || networkFetch;
        }),
    );
});
