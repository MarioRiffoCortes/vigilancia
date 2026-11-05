// Service worker mínimo: permite instalar el portal como aplicación.
// No guarda nada en caché: las cámaras son en vivo y siempre se piden a la red.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', e => {
  if (e.request.mode !== 'navigate') return;   // video, datos y archivos: directo a la red
  e.respondWith(fetch(e.request).catch(() => new Response(
    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
    '<title>Vigilancia</title><body style="margin:0;display:grid;place-items:center;height:100vh;background:#101820;color:#D9E1E8;font:16px system-ui;text-align:center">' +
    '<div><h1 style="font-size:22px">Sin conexión con el PC de la casa</h1>' +
    '<p style="color:#7E8E9F">Revisa que el PC esté encendido, con Laragon abierto, y que Tailscale esté activo.</p>' +
    '<button onclick="location.reload()" style="margin-top:12px;padding:10px 18px;border:0;border-radius:10px;background:#F2A93B;font:600 15px system-ui">Reintentar</button></div>',
    {headers: {'Content-Type': 'text/html; charset=utf-8'}})));
});
