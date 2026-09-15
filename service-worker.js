/**
 * LUB-TEK Service Worker v3.2.4
 * Cache apenas assets estáticos — NUNCA intercepta HTML/navegação/API.
 * JS/CSS: network-first para não servir código antigo com bugs.
 */
const CACHE_NAME = 'lub-tek-static-v3.2.6';

const STATIC_ASSETS = [
  './assets/img/system/img_69581d7fcdbbf.jpeg',
  './assets/css/responsive.css',
  './assets/pwa/manifest.webmanifest'
];

function isStaticAsset(url) {
  const path = new URL(url).pathname;
  return /\.(js|css|png|jpe?g|gif|webp|svg|woff2?|ico|json)$/i.test(path);
}

function isApiOrMutation(request) {
  return request.method !== 'GET' || request.url.includes('api.php');
}

function isNavigation(request) {
  return request.mode === 'navigate' ||
    request.destination === 'document' ||
    /\.php(\?|$)/i.test(new URL(request.url).pathname);
}

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS).catch(() => {}))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;

  if (isApiOrMutation(request) || isNavigation(request)) {
    return;
  }

  if (!isStaticAsset(request.url)) {
    return;
  }

  const isJsCss = /\.(js|css)$/i.test(new URL(request.url).pathname);

  if (isJsCss) {
    event.respondWith(
      fetch(request).then(response => {
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, clone)).catch(() => {});
        }
        return response;
      }).catch(() => caches.match(request))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then(cached => {
      if (cached) return cached;
      return fetch(request).then(response => {
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, clone)).catch(() => {});
        }
        return response;
      }).catch(() => caches.match(request));
    })
  );
});
