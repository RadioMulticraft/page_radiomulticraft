// Minimaler Service Worker, nur damit Browser den News-Editor als
// installierbare App erkennen ("Add to Home Screen" / "App installieren").
// Er cached bewusst nichts und greift nicht in Netzwerk-Anfragen ein -
// news.json soll ja immer live und aktuell geladen werden, nicht aus einem
// Offline-Cache.
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Kein fetch-Handler mit eigener Cache-Logik - Anfragen laufen ganz normal
// durch, als gäbe es den Service Worker nicht. Die bloße Registrierung
// reicht für die Installierbarkeits-Kriterien der Browser aus.
