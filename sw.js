/**
 * LUB-TEK PWA Service Worker v3.2.4
 * JS/CSS: network-first (evita cache de bugs antigos)
 * Imagens: cache-first
 * Nunca intercepta API / navegação PHP
 */
const CACHE_NAME = 'lubtek-pwa-v3.2.6';

const STATIC_ASSETS = [
  './assets/pwa/manifest.webmanifest',
  './assets/img/system/img_69581d7fcdbbf.jpeg',
  './assets/css/responsive.css'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      // cache.addAll() é atômico: se um único arquivo falhar (404/offline), NENHUM
      // dos outros é cacheado. Cacheamos individualmente para que a falha de um
      // asset não impeça o cache dos demais.
      .then((cache) => Promise.all(STATIC_ASSETS.map((url) => cache.add(url).catch(() => {}))))
      .then(() => self.skipWaiting())
      .catch(() => {})
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim()).catch(() => {})
  );
});

function isApiOrNavigation(request) {
  if (request.method !== 'GET') return true;
  const url = new URL(request.url);
  if (url.pathname.includes('api.php')) return true;
  if (request.mode === 'navigate' || request.destination === 'document') return true;
  if (/\.php(\?|$)/i.test(url.pathname)) return true;
  return false;
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (isApiOrNavigation(req)) {
    return;
  }

  const url = new URL(req.url);
  const isJsCss = /\.(js|css)$/i.test(url.pathname);
  const isImage = /\.(png|jpe?g|gif|webp|svg|ico|woff2?)$/i.test(url.pathname);

  if (isJsCss) {
    // Network-first: sempre tenta rede para pegar correções
    event.respondWith(
      fetch(req).then((response) => {
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, clone)).catch(() => {});
        }
        return response;
      }).catch(() => caches.match(req))
    );
    return;
  }

  if (isImage || /\.json$/i.test(url.pathname) || /manifest\.(webmanifest|php)$/i.test(url.pathname)) {
    event.respondWith(
      caches.match(req).then((cached) => {
        if (cached) return cached;
        return fetch(req).then((response) => {
          if (response && response.status === 200 && response.type === 'basic') {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, clone)).catch(() => {});
          }
          return response;
        }).catch(() => cached);
      })
    );
  }
});
