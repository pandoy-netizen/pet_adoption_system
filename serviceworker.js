const CACHE_NAME = 'rescue-app-v1';

// Install event - caches nothing initially to prevent PHP data issues
self.addEventListener('install', event => {
    self.skipWaiting();
});

// Activate event - cleans up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
});

// Fetch event - Always goes to the network first to get fresh PHP data
self.addEventListener('fetch', event => {
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});