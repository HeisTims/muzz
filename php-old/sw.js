const CACHE_NAME = 'eazymuze-v4-cache';
const urlsToCache = [
    './',
    './index.html',
    './css/style.css',
    './js/app.js',
    './js/api.js',
    './js/globals.js',
    './js/data.js',
    './js/utils.js',
    './js/feed.js',
    './js/explore.js',
    './js/invites.js',
    './js/messages.js',
    './js/blackmarket.js',
    './js/profile.js',
    './js/help.js',
    './assets/img/logo.png',
    './assets/img/logo1.png'
];

self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
    );
});

self.addEventListener('fetch', event => {
    // Only handle GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Only handle HTTP/HTTPS requests to prevent errors with chrome-extension://, data://, etc.
    if (!event.request.url.startsWith('http')) {
        return;
    }

    const url = new URL(event.request.url);

    // Bypass the service worker for dynamic PHP pages and API endpoints
    if (
        url.pathname.endsWith('.php') || 
        url.pathname.includes('/api/') || 
        url.pathname.includes('/webhook')
    ) {
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                return fetch(event.request).catch(error => {
                    console.warn('Fetch failed for static asset:', event.request.url, error);
                    // Return a standard 404 response instead of throwing a TypeError promise rejection
                    return new Response('Asset not available offline', {
                        status: 404,
                        statusText: 'Not Found'
                    });
                });
            })
    );
});

self.addEventListener('activate', event => {
    self.clients.claim();
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Handle PWA notification click event (e.g. redirecting to whispers page)
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
            for (let client of clientList) {
                if ('focus' in client) {
                    client.focus();
                    client.postMessage({ action: 'navigate', view: 'messages' });
                    return;
                }
            }
            if (clients.openWindow) {
                return clients.openWindow('./#messages');
            }
        })
    );
});
