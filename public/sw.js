self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET' || request.mode !== 'navigate') {
        return;
    }

    event.respondWith(fetch(request).catch(() => new Response(
        '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="theme-color" content="#BC0001"><title>Offline · Ray-Ban Moments</title><body style="margin:0;padding:3rem 1.5rem;background:#BC0001;color:white;font:16px/1.5 sans-serif;text-align:center"><h1>You’re offline</h1><p>Reconnect to the internet and try again.</p></body></html>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } },
    )));
});
