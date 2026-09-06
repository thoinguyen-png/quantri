const APP_VERSION = new URL(self.location.href).searchParams.get('v') || 'dev';
const CACHE_SCHEMA_VERSION = 'camera-flow-v2';
const STATIC_CACHE = `maxsim-static-${APP_VERSION}-${CACHE_SCHEMA_VERSION}`;

const STATIC_PATHS = [
    /^\/build\/assets\//,
    /^\/icons\//,
    /^\/manifest\.json$/,
];

const DYNAMIC_PATHS = [
    /^\/$/,
    /^\/login/,
    /^\/logout/,
    /^\/register/,
    /^\/dashboard/,
    /^\/me/,
    /^\/settings/,
    /^\/profile/,
    /^\/attendance/,
    /^\/attendance-reports/,
    /^\/attendance-statistics/,
    /^\/attendance-supplements/,
    /^\/face/,
    /^\/qr/,
    /^\/users/,
    /^\/shift-assignments/,
    /^\/shifts/,
    /^\/leave-requests/,
    /^\/payrolls/,
    /^\/admin/,
    /^\/auth/,
    /^\/session-expired/,
];

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (keys) {
                return Promise.all(keys.map(function (key) {
                    if (key !== STATIC_CACHE) {
                        return caches.delete(key);
                    }

                    return Promise.resolve();
                }));
            })
            .then(function () {
                return clients.claim();
            })
    );
});

self.addEventListener('message', function (event) {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', function (event) {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    const acceptsHtml = request.mode === 'navigate'
        || (request.headers.get('accept') || '').includes('text/html');
    const isDynamicPath = DYNAMIC_PATHS.some(function (pattern) {
        return pattern.test(url.pathname);
    });

    if (acceptsHtml || isDynamicPath) {
        event.respondWith(fetch(request, { cache: 'no-store' }));
        return;
    }

    const isStaticAsset = STATIC_PATHS.some(function (pattern) {
        return pattern.test(url.pathname);
    });

    if (!isStaticAsset) {
        return;
    }

    event.respondWith(
        fetch(request, { cache: 'no-cache' })
            .then(function (response) {
                if (response && response.ok) {
                    const clone = response.clone();
                    caches.open(STATIC_CACHE).then(function (cache) {
                        cache.put(request, clone);
                    });
                }

                return response;
            })
            .catch(function () {
                return caches.match(request);
            })
    );
});
