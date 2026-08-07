// Service Worker Zdajnik - PWA (cache-first dla statyki, network-first dla stron
// dynamicznych z offline fallbackiem).
//
// Podbicie CACHE_VERSION przy każdym deployu, który powinien wymusić u
// użytkowników odświeżenie cache'a (np. zmiana logiki SW albo listy PRECACHE_URLS).
// Wersjonowanie samych plików CSS/JS (main.css?v=..., main.js?v=...) jest już
// obsłużone przez filemtime() w head.php/footer.php - dlatego te URL-e NIE są
// tu precache'owane na sztywno, tylko łapane dynamicznie przy pierwszym fetchu.
const CACHE_VERSION = 'v2';
const STATIC_CACHE = `zdajnik-static-${CACHE_VERSION}`;
const CACHE_PREFIX = 'zdajnik-';
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
  OFFLINE_URL,
  '/site.webmanifest',
  '/images/company/favicon.ico',
  '/images/company/favicon-32x32.png',
  '/images/company/favicon-48x48.png',
  '/images/company/apple-touch-icon.png',
  '/images/company/android-chrome-192x192.png',
  '/images/company/android-chrome-512x512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith(CACHE_PREFIX) && key !== STATIC_CACHE)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

function isStaticAsset(request) {
  if (['style', 'script', 'font', 'image'].includes(request.destination)) {
    return true;
  }

  const path = new URL(request.url).pathname;
  return path.startsWith('/js/') || path.startsWith('/scss/') || path.startsWith('/images/');
}

async function cacheFirst(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cached = await cache.match(request);

  // Stale-while-revalidate: serwujemy natychmiast z cache'a (jeśli jest), a w tle
  // dobijamy sieć i uaktualniamy wpis pod tym samym URL-em na następny raz.
  const networkFetch = fetch(request)
    .then((response) => {
      if (response.ok && response.type === 'basic') {
        cache.put(request, response.clone());
      }
      return response;
    })
    .catch(() => null);

  return cached || (await networkFetch) || Response.error();
}

async function networkFirstNavigation(request) {
  try {
    return await fetch(request);
  } catch (error) {
    const cache = await caches.open(STATIC_CACHE);
    const offline = await cache.match(OFFLINE_URL);
    return offline || Response.error();
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Tylko GET - żądania modyfikujące (POST/PUT/DELETE, np. zapisywanie odpowiedzi
  // w quizie) muszą zawsze iść na żywo do serwera, nigdy przez cache.
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  // Zasoby z innych originów (Google Fonts, FontAwesome CDN, Chart.js) - zostawiamy
  // przeglądarce, żeby nie komplikować cache'a odpowiedziami "opaque".
  if (url.origin !== self.location.origin) {
    return;
  }

  // Nawigacja (wejście/odświeżenie strony z pytaniami, testem, quizem itd.) -
  // zawsze najpierw sieć, żeby nigdy nie serwować nieaktualnych danych. Cache
  // wchodzi do gry wyłącznie jako fallback offline.
  if (request.mode === 'navigate') {
    event.respondWith(networkFirstNavigation(request));
    return;
  }

  // Statyczne assety (CSS/JS/fonty/ikony) - cache-first z odświeżeniem w tle.
  if (isStaticAsset(request)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  // Wszystko inne (np. dynamiczne wywołania JSON typu /search/topics) - zostaw
  // przeglądarce, nie cache'ujemy danych, które mogą się zmieniać między żądaniami.
});
