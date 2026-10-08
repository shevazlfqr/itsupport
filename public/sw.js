const CACHE_VERSION = "itsupport-static-v7";
const STATIC_ASSETS = [
  "/offline.html",
  "/manifest.webmanifest",
  "/assets/styles.css",
  "/assets/app.js",
  "/assets/pwa.css",
  "/assets/pwa.js",
  "/assets/ascii-field.svg",
  "/icons/icon-192.svg",
  "/icons/icon-512.svg",
  "/icons/icon-maskable.svg",
];

const isSameOrigin = (request) => new URL(request.url).origin === self.location.origin;
const isStaticAsset = (pathname) =>
  pathname.startsWith("/assets/") ||
  pathname.startsWith("/icons/") ||
  pathname === "/manifest.webmanifest" ||
  pathname === "/offline.html";

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) =>
      Promise.all(STATIC_ASSETS.map((asset) => cache.add(asset).catch(() => undefined))),
    ),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key.startsWith("itsupport-static-") && key !== CACHE_VERSION).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("message", (event) => {
  if (event.data?.type === "SKIP_WAITING") self.skipWaiting();
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET" || !isSameOrigin(request)) return;

  const url = new URL(request.url);
  if (isStaticAsset(url.pathname)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
          if (!response.ok) return response;
          const copy = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
          return response;
        });
      }),
    );
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(new Request(request, { cache: "no-store" })).catch(() => caches.match("/offline.html")),
    );
  }
});
