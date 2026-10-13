const CACHE_STATIC = "sava-static-v5";

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_STATIC).then((cache) => cache.addAll(["/manifest.webmanifest"]))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") return;

  const url = new URL(event.request.url);

  // Nunca cachear navegación, API ni chunks de Next.js (cambian en cada build).
  if (event.request.mode === "navigate") return;
  if (url.pathname.startsWith("/api/")) return;
  if (url.pathname.startsWith("/auth/")) return;
  if (url.pathname.startsWith("/_next/")) return;

  const isStatic = url.pathname.startsWith("/branding/") || url.pathname.endsWith(".webmanifest");

  if (!isStatic) return;

  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached;
      return fetch(event.request).then((response) => {
        if (response?.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_STATIC).then((cache) => cache.put(event.request, clone));
        }
        return response;
      });
    })
  );
});
