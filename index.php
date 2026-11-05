<?php
// Portal de vigilancia — lee la lista de cámaras desde go2rtc (go2rtc/go2rtc.yaml)
require __DIR__ . '/config.php';

$api = 'http://127.0.0.1:1984/api/streams';
$ctx = stream_context_create(['http' => ['timeout' => 2]]);
$raw = @file_get_contents($api, false, $ctx);
$streams = $raw ? json_decode($raw, true) : null;

$camaras = [];
if (is_array($streams)) {
    $nombres = array_keys($streams);
    natsort($nombres);
    foreach ($nombres as $n) {
        if (substr($n, -3) === '_hd') continue;
        $camaras[] = [
            'id'    => $n,
            'hd'    => isset($streams[$n . '_hd']) ? $n . '_hd' : $n,
            'label' => $NOMBRES[$n] ?? ucwords(str_replace(['_', '-'], ' ', $n)),
        ];
    }
    // Si solo hay flujos "_hd", úsalos igual
    if (!$camaras) {
        foreach ($nombres as $n) {
            $camaras[] = ['id' => $n, 'hd' => $n, 'label' => ucwords(str_replace(['_', '-', ' hd'], [' ', ' ', ''], $n))];
        }
    }
}
$activo = is_array($streams);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#101820">
<title>Vigilancia · Casa</title>
<link rel="manifest" href="manifest.json">
<link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32.png">
<link rel="icon" type="image/svg+xml" href="img/icon.svg">
<link rel="apple-touch-icon" href="img/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Vigilancia">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --ink:#101820;        /* fondo: azul pizarra nocturno */
  --deck:#16212C;       /* paneles */
  --rail:#243242;       /* bordes */
  --fog:#D9E1E8;        /* texto */
  --dim:#7E8E9F;        /* texto secundario */
  --lamp:#F2A93B;       /* ámbar: selección y controles activos */
  --live:#4ADE80;       /* en vivo */
  --alarm:#F0565B;      /* sin señal */
  --r:12px;
  --cond:"Barlow Condensed","Arial Narrow",system-ui,sans-serif;
  --sans:"Barlow",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  background:var(--ink);
  background-image:radial-gradient(120% 80% at 50% -10%, #116fd4 0%, var(--ink) 60%);
  color:var(--fog);font:15px/1.4 var(--sans);display:flex;flex-direction:column;
  -webkit-tap-highlight-color:transparent;
}
button{font:inherit;color:inherit}
:focus-visible{outline:2px solid var(--lamp);outline-offset:2px}

/* ---------- Barra superior ---------- */
header{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:16px;padding:12px 18px;border-bottom:1px solid var(--rail);background:rgba(22,33,44,.85)}
.brand{display:flex;align-items:center;gap:12px;min-width:0}
.brand svg{flex:none;color:var(--lamp)}
.brand h1{font:700 22px/1 var(--cond);letter-spacing:.01em}
.brand small{display:block;font:500 13px/1.2 var(--sans);color:var(--dim);margin-top:3px}
.status{display:flex;align-items:baseline;gap:14px}
.time{font:600 34px/1 var(--cond);font-variant-numeric:tabular-nums;letter-spacing:.02em}
.date{font:500 14px/1 var(--sans);color:var(--dim)}
.online{display:inline-flex;align-items:center;gap:6px;font:600 15px/1 var(--cond);color:var(--dim);padding:6px 10px;border:1px solid var(--rail);border-radius:999px;font-variant-numeric:tabular-nums}
.online i{width:8px;height:8px;border-radius:50%;background:var(--dim)}
.online.ok{color:var(--fog)} .online.ok i{background:var(--live);box-shadow:0 0 0 3px rgba(74,222,128,.15)}
.online.bad i{background:var(--alarm)}
.tools{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
.install{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--lamp);background:transparent;color:var(--lamp);font:600 15px/1 var(--cond);letter-spacing:.03em;padding:8px 12px;border-radius:10px;cursor:pointer}
.install[hidden]{display:none}
.install svg{width:17px;height:17px}
.install:hover{background:var(--lamp);color:#1B1305}
.seg{display:flex;background:var(--ink);border:1px solid var(--rail);border-radius:10px;padding:3px;gap:2px}
.seg button{border:0;background:transparent;color:var(--dim);font:600 15px/1 var(--cond);letter-spacing:.03em;padding:7px 12px;border-radius:7px;cursor:pointer}
.seg button:hover{color:var(--fog)}
.seg button.on{background:var(--lamp);color:#1B1305}
.seg .link{color:var(--dim);font:600 15px/1 var(--cond);letter-spacing:.03em;padding:7px 12px;border-radius:7px;text-decoration:none}
.seg .link:hover{color:var(--fog)}

main{flex:1;padding:12px;min-height:0;container-type:size}

/* ---------- Monitores ---------- */
.grid{display:grid;gap:10px;height:100%;grid-template-columns:repeat(var(--cols),minmax(0,1fr));grid-auto-rows:minmax(0,1fr)}

/* Grilla: el bloque completo toma la proporción exacta de las cámaras (16:9 cada una),
   así quedan pegadas, sin franjas negras, y el bloque se centra en la pantalla */
.grid:not(.focus){
  gap:2px;margin:auto;background:var(--rail);border:1px solid var(--rail);border-radius:var(--r);overflow:hidden;
  width:min(100cqw, calc(100cqh * var(--ratio)));
  height:min(100cqh, calc(100cqw / var(--ratio)));
}
.grid:not(.focus) .tile{border:0;border-radius:0}
.grid:not(.focus) .tile:hover{box-shadow:inset 0 0 0 2px var(--lamp)}
.grid:not(.focus) .frame{width:100%;height:100%}
.tile{position:relative;background:#05080B;border:1px solid var(--rail);border-radius:var(--r);overflow:hidden;min-height:0;container-type:size}
.frame{position:absolute;inset:0;margin:auto;width:min(100cqw,calc(100cqh * 16 / 9));height:min(100cqh,calc(100cqw * 9 / 16))}
.frame video-stream{display:block;width:100%;height:100%}
.frame video{object-fit:fill;background:#05080B}
.tile video::-webkit-media-controls{display:none !important}
.tile video-stream .info{display:none}
.tile .hit{position:absolute;inset:0;z-index:2;cursor:zoom-in}
.tile.moving::after{content:"";position:absolute;inset:0;z-index:4;pointer-events:none;border-radius:inherit;box-shadow:inset 0 0 0 3px var(--alarm)}   /* encima del video */

/* Rótulo en pantalla, como el de un DVR */
.osd{position:absolute;z-index:3;pointer-events:none;display:flex;align-items:center;gap:8px;font-family:var(--cond)}
.osd.top{left:0;right:0;top:0;padding:10px 12px;justify-content:flex-start;background:linear-gradient(rgba(5,8,11,.7),transparent)}
.osd.bottom{left:0;right:0;bottom:0;padding:22px 12px 10px;background:linear-gradient(transparent,rgba(5,8,11,.75))}
.ch{font:600 13px/1 var(--cond);letter-spacing:.06em;color:var(--dim);border:1px solid rgba(217,225,232,.25);border-radius:5px;padding:3px 6px}
.state{display:flex;align-items:center;gap:6px;font:600 14px/1 var(--cond);letter-spacing:.02em}
.state i{width:8px;height:8px;border-radius:50%;background:var(--dim)}
.tile[data-state=live] .state i{background:var(--live);animation:beat 2s ease-in-out infinite}
.tile[data-state=lost] .state{color:var(--alarm)} .tile[data-state=lost] .state i{background:var(--alarm)}
.tile[data-state=wait] .state{color:var(--dim)}
@keyframes beat{50%{opacity:.35}}
.name{font:700 22px/1 var(--cond);letter-spacing:.01em;text-shadow:0 1px 2px rgba(0,0,0,.6)}
.tile:hover{border-color:#3A4C60}

/* Controles de la vista enfocada */
.ctrl{position:absolute;right:12px;bottom:10px;z-index:4;display:none;gap:6px}
.ctrl button{width:40px;height:40px;border-radius:10px;border:1px solid rgba(217,225,232,.18);background:rgba(16,24,32,.78);cursor:pointer;display:grid;place-items:center;color:var(--fog)}
.ctrl button:hover{border-color:var(--lamp);color:var(--lamp)}
.ctrl svg{width:20px;height:20px}

/* ---------- Enfoque: una cámara grande, las demás siguen en vivo ---------- */
.grid.focus{grid-template-columns:minmax(0,1fr) clamp(200px,22vw,340px);grid-template-rows:repeat(var(--side),minmax(0,1fr))}
.grid.focus .tile.main{grid-column:1;grid-row:1 / -1}
.grid.focus .tile.main .hit{cursor:default}
.grid.focus .tile.main .ctrl{display:flex}
.grid.focus .tile.main .name{font-size:30px}
.grid.focus .tile:not(.main){grid-column:2}
.grid.focus .tile:not(.main) .hit{cursor:pointer}
.grid.focus .tile:not(.main):hover{border-color:var(--lamp)}
.grid.focus .tile:not(.main) .name{font-size:18px}
.grid.focus .tile:not(.main) .state b{display:none}
.tile:fullscreen{border:0;border-radius:0;box-shadow:none}
.tile:fullscreen .ctrl{display:flex}

.empty{max-width:560px;margin:10vh auto;background:var(--deck);border:1px solid var(--rail);border-radius:var(--r);padding:28px}
.empty h2{font:700 26px/1.1 var(--cond);margin-bottom:10px}
.empty p{color:var(--dim)}
.empty ol{margin:14px 0 0 20px;color:var(--dim)}
.empty li{margin:6px 0}
.empty code{background:var(--ink);border:1px solid var(--rail);padding:1px 6px;border-radius:5px;color:var(--fog)}

/* ---------- Celular ---------- */
@media (max-width:760px){
  header{grid-template-columns:1fr auto;gap:10px 12px;padding:10px 12px;position:sticky;top:0;z-index:20;background:rgba(22,33,44,.96)}
  .brand small{display:none}
  .brand h1{font-size:20px}
  .status{display:contents}
  .time{grid-column:2;grid-row:1;justify-self:end;font-size:26px}
  .date{display:none}
  .online{grid-column:1;grid-row:2;justify-self:start}
  .online .lbl{display:none}
  .tools{grid-column:2;grid-row:2}
  .seg button{padding:8px 12px}
}
@media (max-width:640px){
  main{padding:8px;container-type:normal}
  .grid{height:auto;grid-template-columns:minmax(0,1fr);grid-auto-rows:auto;gap:8px}
  .grid:not(.focus){width:auto;height:auto;gap:2px}
  .tile{aspect-ratio:16/9}
  .grid.focus{grid-template-columns:repeat(var(--side),minmax(0,1fr));grid-template-rows:none;grid-auto-rows:auto}
  .grid.focus .tile.main{grid-column:1 / -1;grid-row:auto;order:-1;box-shadow:none}
  .grid.focus .tile.main .name{font-size:24px}
  .grid.focus .tile:not(.main){grid-column:auto;border-radius:9px}
  .grid.focus .tile:not(.main) .osd.top{padding:5px 6px}
  .grid.focus .tile:not(.main) .osd.bottom{padding:12px 6px 5px}
  .grid.focus .tile:not(.main) .ch{display:none}
  .grid.focus .tile:not(.main) .name{font-size:14px}
  .ctrl button{width:42px;height:42px}
}
/* ---------- Ficha técnica (solo celular, en Enfoque) ---------- */
.specs{display:none}
@media (max-width:640px){
  body.focusing .specs{display:block}
  .specs{margin-top:10px;background:var(--deck);border:1px solid var(--rail);border-radius:var(--r);padding:14px 14px 12px}
  .specs-head{display:flex;align-items:center;gap:10px;padding-bottom:12px;border-bottom:1px solid var(--rail)}
  .specs-head .nm{font:700 22px/1 var(--cond);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .specs-head .state{font-size:15px}
  .specs[data-state=live] .state i{background:var(--live)}
  .specs[data-state=lost] .state{color:var(--alarm)} .specs[data-state=lost] .state i{background:var(--alarm)}
  .specs[data-state=wait] .state{color:var(--dim)}
  .specs dl{display:grid;grid-template-columns:1fr 1fr;gap:14px 16px;padding:14px 0}
  .specs dt{font:500 13px/1.2 var(--sans);color:var(--dim);margin-bottom:3px}
  .specs dd{font:600 19px/1.15 var(--cond);font-variant-numeric:tabular-nums;color:var(--fog);overflow-wrap:anywhere}
  .specs dd small{font:500 13px/1 var(--sans);color:var(--dim);margin-left:4px}
  .specs .audio{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:12px;border-top:1px solid var(--rail)}
  .specs .audio button{flex:none;display:inline-flex;align-items:center;gap:8px;border:1px solid var(--rail);background:var(--ink);color:var(--fog);font:600 15px/1 var(--cond);letter-spacing:.02em;padding:10px 14px;border-radius:10px;cursor:pointer}
  .specs .audio button svg{width:18px;height:18px}
  .specs .audio button.on{background:var(--lamp);border-color:var(--lamp);color:#1B1305}
  .specs .audio button:disabled{opacity:.4;cursor:default}
}
/* ---------- Rótulos proporcionales al tamaño de cada cámara ---------- */
/* (cqw = % del ancho de la cámara: en ventanas chicas los textos se achican junto con la imagen) */
.osd.top{padding:clamp(4px,2cqw,10px) clamp(6px,2.4cqw,12px)}
.osd.bottom{padding:clamp(10px,5cqw,22px) clamp(6px,2.4cqw,12px) clamp(4px,2cqw,10px)}
.ch{font-size:clamp(10px,2.4cqw,13px);padding:2px 5px}
.state{font-size:clamp(11px,2.6cqw,14px)}
.name{font-size:clamp(13px,4.4cqw,22px)}
.grid.focus .tile.main .name{font-size:clamp(16px,5cqw,30px)}
.grid.focus .tile:not(.main) .name{font-size:clamp(12px,6cqw,18px)}
.ctrl{right:clamp(6px,1.4cqw,12px);bottom:clamp(6px,1.4cqw,10px)}
.ctrl button{width:clamp(30px,5cqw,40px);height:clamp(30px,5cqw,40px)}
.ctrl svg{width:clamp(16px,2.6cqw,20px);height:clamp(16px,2.6cqw,20px)}
@container (max-width:340px){ .ch{display:none} .state b{display:none} }

/* ---------- Ventana chica en escritorio: barra superior compacta ---------- */
@media (min-width:641px) and (max-height:720px), (min-width:761px) and (max-width:1100px){
  header{padding:6px 12px;gap:12px}
  .brand{gap:9px}
  .brand svg{width:22px;height:22px}
  .brand h1{font-size:18px}
  .brand small{display:none}
  .time{font-size:24px}
  .date{font-size:12px}
  .online{padding:4px 8px;font-size:13px}
  .seg{padding:2px}
  .seg button,.seg .link{padding:5px 9px;font-size:13px}
  main{padding:6px}
}
@media (prefers-reduced-motion:reduce){.tile[data-state=live] .state i{animation:none}}
</style>
</head>
<body>
<header>
  <div class="brand">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7.5 15.5 4l1.6 5.6L4.6 13z"/><path d="m17.1 9.6 3.4-1"/><path d="M7 12.6 8.2 17H4"/><circle cx="12.2" cy="8" r="1.2"/></svg>
    <div><h1>Vigilancia</h1><small>Casa</small></div>
  </div>
  <div class="status">
    <span class="time" id="time">--:--:--</span>
    <span class="date" id="date"></span>
    <?php if ($camaras): ?><span class="online" id="online"><i></i><span class="cnt">0/<?= count($camaras) ?></span><span class="lbl">en línea</span></span><?php endif; ?>
  </div>
  <div class="tools">
    <button class="install" id="install" hidden title="Instalar como aplicación">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>Instalar
    </button>
    <?php if ($camaras): ?>
    <div class="seg" id="quality" role="group" aria-label="Calidad del video">
      <button data-q="sd">SD</button><button data-q="hd">HD</button><button data-q="uhd" title="Tiempo real: menos retraso, puede saltar cuadros">UHD</button>
    </div>
    <?php endif; ?>
    <?php if (count($camaras) > 1): ?>
    <div class="seg" id="views" role="group" aria-label="Vista">
      <button data-v="grid">Grilla</button><button data-v="focus">Enfoque</button>
    </div>
    <div class="seg">
      <button id="auto" aria-pressed="false" title="Detector de personas: pone en Enfoque la cámara donde aparece alguien">Auto</button>
    </div>
    <?php endif; ?>
    <div class="seg">
      <a class="link" href="grabaciones.php" title="Ver y descargar grabaciones del DVR">Grabaciones</a>
    </div>
  </div>
</header>

<main>
<?php if (!$activo): ?>
  <div class="empty">
    <h2>El servidor de video está apagado</h2>
    <p>Enciéndelo para que el portal pueda mostrar las cámaras.</p>
    <ol>
      <li>En la carpeta <code>vigilancia</code>, abre <code>Camaras ON-OFF.bat</code>.</li>
      <li>Recarga esta página.</li>
    </ol>
  </div>
<?php elseif (!$camaras): ?>
  <div class="empty">
    <h2>No hay cámaras configuradas</h2>
    <p>Agrega tus cámaras en <code>go2rtc/go2rtc.yaml</code> y vuelve a encender el servidor de video.</p>
  </div>
<?php else: ?>
  <div class="grid" id="grid"></div>
  <section class="specs" id="specs" data-state="wait" aria-label="Datos técnicos de la cámara">
    <div class="specs-head">
      <span class="ch" id="sCh">CH</span><span class="nm" id="sName"></span>
      <span class="state"><i></i><b id="sState">Conectando</b></span>
    </div>
    <dl>
      <div><dt>Resolución</dt><dd id="sRes">—</dd></div>
      <div><dt>Bitrate</dt><dd id="sBit">—</dd></div>
      <div><dt>Cuadros por segundo</dt><dd id="sFps">—</dd></div>
      <div><dt>Códec de video</dt><dd id="sCodec">—</dd></div>
      <div><dt>Retraso</dt><dd id="sLag">—</dd></div>
      <div><dt>Cuadros perdidos</dt><dd id="sDrop">—</dd></div>
      <div><dt>Flujo</dt><dd id="sFlow">—</dd></div>
      <div><dt>Conexiones</dt><dd id="sView">—</dd></div>
    </dl>
    <div class="audio">
      <div><dt>Salida de audio</dt><dd id="sAudio">—</dd></div>
      <button id="sMute" disabled></button>
    </div>
  </section>
<?php endif; ?>
</main>

<script type="module">
import './js/video-stream.js';

// ---------- Instalar como aplicación ----------
if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
const installBtn = document.getElementById('install');
let installEvt = null;
addEventListener('beforeinstallprompt', e => { e.preventDefault(); installEvt = e; installBtn.hidden = false; });
installBtn.addEventListener('click', async () => {
  if (!installEvt) return;
  installEvt.prompt();
  await installEvt.userChoice;
  installEvt = null; installBtn.hidden = true;
});
addEventListener('appinstalled', () => { installBtn.hidden = true; });
const CAMS = <?= json_encode($camaras, JSON_UNESCAPED_UNICODE) ?>;
const MODE = 'mse,webrtc,hls,mjpeg';
const WS = `${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.hostname}:1984/api/ws?src=`;

const ICON = {
  prev: '<path d="m15 18-6-6 6-6"/>',
  next: '<path d="m9 18 6-6-6-6"/>',
  full: '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
  close: '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>'
};
const svg = k => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICON[k]}</svg>`;

// Reloj
const timeEl = document.getElementById('time'), dateEl = document.getElementById('date');
const tick = () => {
  const d = new Date();
  timeEl.textContent = d.toLocaleTimeString('es-CL', {hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false});
  dateEl.textContent = d.toLocaleDateString('es-CL', {weekday:'short', day:'numeric', month:'short'});
};
tick(); setInterval(tick, 1000);

if (CAMS.length) {
  const grid = document.getElementById('grid');
  const n = CAMS.length;
  grid.style.setProperty('--cols', n <= 1 ? 1 : n <= 4 ? 2 : n <= 9 ? 3 : 4);
  grid.style.setProperty('--side', Math.max(n - 1, 1));
  const cols = n <= 1 ? 1 : n <= 4 ? 2 : n <= 9 ? 3 : 4, rows = Math.ceil(n / cols);
  grid.style.setProperty('--ratio', (16 * cols) / (9 * rows));

  const store = {
    get(k){ try { return localStorage.getItem(k); } catch(e){ return null; } },
    set(k,v){ try { localStorage.setItem(k,v); } catch(e){} }
  };
  let quality = ['sd', 'uhd'].includes(store.get('cams.q')) ? store.get('cams.q') : 'hd';   // HD por defecto
  const streamOf = c => quality === 'sd' ? c.id : c.hd;
  const channel = (c, i) => (c.id.match(/\d+/) || [i + 1])[0];

  function makePlayer(c) {
    const v = document.createElement('video-stream');
    v.mode = quality === 'uhd' ? 'webrtc,mse,hls,mjpeg' : MODE;   // UHD: WebRTC primero, menos retraso
    v.src = WS + encodeURIComponent(streamOf(c));
    return v;
  }

  // Crear cada cámara UNA sola vez. Cambiar de vista solo cambia clases CSS,
  // así los videos nunca se desconectan ni vuelven a cargar.
  const tiles = CAMS.map((c, i) => {
    const t = document.createElement('div');
    t.className = 'tile';
    t.dataset.state = 'wait';
    const f = document.createElement('div');
    f.className = 'frame';
    f.appendChild(makePlayer(c));
    t.appendChild(f);
    t.insertAdjacentHTML('beforeend', `
      <div class="osd top"><span class="ch">CH ${channel(c, i)}</span><span class="state"><i></i><b>Conectando</b></span></div>
      <div class="osd bottom"><span class="name">${c.label}</span></div>
      <div class="hit" aria-label="Ver ${c.label}"></div>
      <div class="ctrl">
        <button data-a="prev" title="Cámara anterior (←)" aria-label="Cámara anterior">${svg('prev')}</button>
        <button data-a="next" title="Cámara siguiente (→)" aria-label="Cámara siguiente">${svg('next')}</button>
        <button data-a="full" title="Pantalla completa" aria-label="Pantalla completa">${svg('full')}</button>
        <button data-a="close" title="Volver a la grilla (Esc)" aria-label="Volver a la grilla">${svg('close')}</button>
      </div>`);
    t.querySelector('.hit').addEventListener('click', () => { if (focus !== i) setFocus(i); });
    t.querySelector('.hit').addEventListener('dblclick', () => { if (focus === i) fullscreen(t); });
    t.querySelector('.ctrl').addEventListener('click', e => {
      const a = e.target.closest('button')?.dataset.a;
      if (a === 'prev') setFocus((focus - 1 + n) % n);
      if (a === 'next') setFocus((focus + 1) % n);
      if (a === 'full') fullscreen(t);
      if (a === 'close') setFocus(-1);
    });
    grid.appendChild(t);
    return t;
  });

  // Estado real de cada cámara: si el video avanza está en vivo; si se detiene, sin señal
  const LABEL = {live: 'En vivo', wait: 'Conectando', lost: 'Sin señal'};
  const online = document.getElementById('online');
  const watch = tiles.map(() => ({t: -1, p: '', at: Date.now()}));
  function setState(t, s) {
    if (t.dataset.state === s) return;
    t.dataset.state = s;
    t.querySelector('.state b').textContent = LABEL[s];
  }
  setInterval(() => {
    const now = Date.now();
    tiles.forEach((t, i) => {
      const v = t.querySelector('video'), w = watch[i];
      if (!v) return;
      const p = v.poster ? v.poster.length + v.poster.slice(-32) : '';   // modo imagen (MJPEG)
      if ((v.currentTime > 0 && v.currentTime !== w.t) || (p && p !== w.p)) { w.t = v.currentTime; w.p = p; w.at = now; setState(t, 'live'); }
      else if (now - w.at > 6000) setState(t, 'lost');
    });
    const ok = tiles.filter(t => t.dataset.state === 'live').length;
    online.querySelector('.cnt').textContent = `${ok}/${n}`;
    online.classList.toggle('ok', ok === n);
    online.classList.toggle('bad', ok < n && tiles.some(t => t.dataset.state === 'lost'));
  }, 1000);

  // ---------- Ficha técnica (celular, modo Enfoque) ----------
  const $ = id => document.getElementById(id);
  const specs = $('specs');
  const mobile = matchMedia('(max-width:640px)');
  const SPK = {
    on:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>',
    off: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="m22 9-6 6M16 9l6 6"/></svg>'
  };
  let sp = {};
  function resetSpecs() { sp = {frames: null, at: 0, bytes: null, bytesAt: 0, src: '', srcInfo: null, tick: 0, jb: null}; }
  resetSpecs();
  const fmtBit = bps => bps >= 1e6 ? `${(bps / 1e6).toFixed(1)}<small>Mbps</small>` : `${Math.round(bps / 1e3)}<small>kbps</small>`;

  function updateSpecs() {
    if (focus < 0 || !mobile.matches) return;
    const c = CAMS[focus], t = tiles[focus], v = t.querySelector('video');
    const src = streamOf(c);
    if (sp.src !== src) { resetSpecs(); sp.src = src; }
    $('sCh').textContent = 'CH ' + channel(c, focus);
    $('sName').textContent = c.label;
    specs.dataset.state = t.dataset.state;
    $('sState').textContent = LABEL[t.dataset.state];
    $('sFlow').innerHTML = {sd: 'SD<small>secundario</small>', hd: 'HD<small>principal</small>', uhd: 'UHD<small>tiempo real</small>'}[quality];
    const mode = t.querySelector('video-stream .mode')?.textContent;
    if (!v) return;

    // Resolución que entrega la cámara
    $('sRes').innerHTML = v.videoWidth ? `${v.videoWidth}×${v.videoHeight}` : '—';

    // Cuadros por segundo y cuadros perdidos (medidos en este navegador)
    const q = v.getVideoPlaybackQuality ? v.getVideoPlaybackQuality() : null;
    const now = performance.now();
    if (q) {
      if (sp.frames !== null && now > sp.at) {
        const fps = (q.totalVideoFrames - sp.frames) * 1000 / (now - sp.at);
        $('sFps').textContent = fps >= 0 ? fps.toFixed(fps < 10 ? 1 : 0) : '—';
      }
      sp.frames = q.totalVideoFrames; sp.at = now;
      $('sDrop').innerHTML = `${q.droppedVideoFrames}<small>de ${q.totalVideoFrames}</small>`;
    }

    // Retraso respecto al video en vivo
    const pc = t.querySelector('video-stream')?.pc;
    if (pc && pc.connectionState === 'connected') {
      // WebRTC: espera en el búfer de recepción + medio viaje de ida y vuelta de la red
      pc.getStats().then(stats => {
        let jb = 0, emitted = 0, rtt = 0;
        stats.forEach(r => {
          if (r.type === 'inbound-rtp' && r.kind === 'video') { jb = r.jitterBufferDelay || 0; emitted = r.jitterBufferEmittedCount || 0; }
          if (r.type === 'candidate-pair' && r.nominated && r.currentRoundTripTime) rtt = r.currentRoundTripTime;
        });
        const prev = sp.jb;
        sp.jb = {jb, emitted};
        if (!prev || emitted <= prev.emitted) return;
        const buf = (jb - prev.jb) / (emitted - prev.emitted);   // promedio del último segundo
        const lag = Math.max(0, buf + rtt / 2);
        if (sp.src === src) $('sLag').innerHTML = lag < 1 ? `${Math.round(lag * 1000)}<small>ms</small>` : `${lag.toFixed(1)}<small>s</small>`;
      }).catch(() => {});
    } else if (v.buffered.length) {
      // MSE: lo que queda en el búfer por delante de la imagen actual
      const lag = Math.max(0, v.buffered.end(v.buffered.length - 1) - v.currentTime);
      $('sLag').innerHTML = lag < 1 ? `${Math.round(lag * 1000)}<small>ms</small>` : `${lag.toFixed(1)}<small>s</small>`;
    }

    // Audio: lo que entrega el DVR y si el navegador lo está reproduciendo
    const srcAudio = sp.srcInfo?.audio;
    const playing = (v.webkitAudioDecodedByteCount || 0) > 0 || (v.audioTracks && v.audioTracks.length > 0);
    const btn = $('sMute');
    if (!srcAudio) {
      $('sAudio').innerHTML = sp.srcInfo ? 'Sin audio<small>la cámara no envía sonido</small>' : '—';
      btn.disabled = true; btn.classList.remove('on'); btn.innerHTML = SPK.off + 'Sin sonido';
    } else {
      $('sAudio').innerHTML = `${srcAudio.codec}${srcAudio.rate ? `<small>${srcAudio.rate / 1000} kHz</small>` : ''}`
        + (playing ? '' : '<small>no compatible con el navegador</small>');
      btn.disabled = !playing;
      btn.classList.toggle('on', playing && !v.muted);
      btn.innerHTML = (playing && !v.muted ? SPK.on + 'Sonando' : SPK.off + 'Activar sonido');
    }
    if (mode) $('sView').innerHTML = `${sp.srcInfo ? sp.srcInfo.viewers : '—'}<small>${mode}</small>`;

    // Cada 2 s: datos del servidor (códec, audio y bytes recibidos del DVR → bitrate)
    if (sp.tick++ % 2) return;
    fetch('info.php?src=' + encodeURIComponent(src), {cache: 'no-store'})
      .then(r => r.ok ? r.json() : null)
      .then(d => {
        if (!d || sp.src !== src) return;
        sp.srcInfo = d;
        const vi = d.video;
        $('sCodec').innerHTML = vi ? `${vi.codec}${vi.profile ? `<small>${vi.profile}${vi.level ? ' ' + vi.level : ''}</small>` : ''}` : '—';
        const tNow = performance.now();
        if (sp.bytes !== null && d.bytes >= sp.bytes && tNow > sp.bytesAt) {
          $('sBit').innerHTML = fmtBit((d.bytes - sp.bytes) * 8000 / (tNow - sp.bytesAt));
        }
        sp.bytes = d.bytes; sp.bytesAt = tNow;
        if (!mode) $('sView').textContent = d.viewers;
      })
      .catch(() => {});
  }
  setInterval(updateSpecs, 1000);

  // Activar o silenciar el sonido de la cámara enfocada (requiere un toque del usuario)
  $('sMute').addEventListener('click', () => {
    const v = focus >= 0 && tiles[focus].querySelector('video');
    if (!v) return;
    tiles.forEach(t => { const o = t.querySelector('video'); if (o && o !== v) o.muted = true; });
    v.muted = !v.muted;
    v.play().catch(() => {});
    updateSpecs();
  });

  // Cambiar SD/HD/UHD: es el único caso en que los videos se reconectan (cambia el flujo)
  function setQuality(q) {
    if (q !== quality) {
      quality = q; store.set('cams.q', q);
      tiles.forEach((t, i) => {
        const old = t.querySelector('video-stream');
        old.replaceWith(makePlayer(CAMS[i]));
        try { old.ondisconnect(); } catch (e) {}
        watch[i] = {t: -1, p: '', at: Date.now()};
        setState(t, 'wait');
        resetSpecs();
      });
    }
    document.querySelectorAll('#quality button').forEach(b => b.classList.toggle('on', b.dataset.q === quality));
  }
  document.querySelectorAll('#quality button').forEach(b => b.onclick = () => setQuality(b.dataset.q));
  setQuality(quality);

  let focus = -1, last = 0, pushed = false, ignorePop = false, autoFocus = false;

  function apply() {
    grid.classList.toggle('focus', focus >= 0);
    document.body.classList.toggle('focusing', focus >= 0);
    if (focus >= 0) resetSpecs();
    tiles.forEach((t, i) => t.classList.toggle('main', i === focus));
    document.querySelectorAll('#views button').forEach(b =>
      b.classList.toggle('on', (b.dataset.v === 'focus') === (focus >= 0)));
    if (focus >= 0) last = focus;
  }

  function setFocus(i, fromHistory = false) {
    if (i === focus) return;
    autoFocus = false;   // el detector lo vuelve a marcar si fue él quien cambió
    const wasGrid = focus < 0;
    if (document.fullscreenElement) document.exitFullscreen?.();
    focus = i; apply();
    resume();
    if (fromHistory) { pushed = false; return; }
    // En el celular, el botón "atrás" vuelve a la grilla en vez de salir del portal
    if (wasGrid && i >= 0) { history.pushState({camFocus: true}, ''); pushed = true; }
    else if (i < 0 && pushed) { pushed = false; ignorePop = true; history.back(); }
  }
  addEventListener('popstate', () => {
    if (ignorePop) { ignorePop = false; return; }
    if (focus >= 0) setFocus(-1, true);
  });

  // Si el navegador pausó algún video al reacomodar la vista, se reanuda (sin reconectar)
  function resume() {
    requestAnimationFrame(() => tiles.forEach(t => {
      const v = t.querySelector('video');
      if (v && v.paused && v.readyState > 1) v.play().catch(() => {});
    }));
  }

  function fullscreen(t) {
    if (document.fullscreenElement) return document.exitFullscreen();
    (t.requestFullscreen || t.webkitRequestFullscreen)?.call(t);
  }

  document.querySelectorAll('#views button').forEach(b => b.onclick = () =>
    setFocus(b.dataset.v === 'focus' ? (focus >= 0 ? focus : last) : -1));

  document.addEventListener('keydown', e => {
    if (focus < 0) return;
    if (e.key === 'Escape' && !document.fullscreenElement) setFocus(-1);
    if (e.key === 'ArrowLeft') setFocus((focus - 1 + n) % n);
    if (e.key === 'ArrowRight') setFocus((focus + 1) % n);
  });

  // Deslizar a los lados sobre la cámara grande para cambiar (celular)
  let sx = null, sy = 0;
  grid.addEventListener('touchstart', e => {
    if (focus < 0 || !e.target.closest('.tile.main')) return;
    sx = e.touches[0].clientX; sy = e.touches[0].clientY;
  }, {passive: true});
  grid.addEventListener('touchend', e => {
    if (sx === null) return;
    const dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
    sx = null;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) setFocus((focus + (dx < 0 ? 1 : -1) + n) % n);
  }, {passive: true});

  // ---------- Detector de personas (botón Auto) ----------
  // Un modelo de reconocimiento (COCO-SSD) busca personas en cada cámara; si aparece una, esa cámara pasa a Enfoque.
  // No reacciona a ramas, sombras ni autos. El modelo se descarga de internet la primera vez que se activa Auto.
  const DET = {score: 0.4, night: 0.6, move: 0.02, hold: 8000, back: 15000, pause: 300};   // score/night: confianza mínima de día y de noche; move: parte del recuadro que debe cambiar; hold/back en ms
  const hits = tiles.map(() => 0);
  const HOME = CAMS.findIndex(c => c.label === 'Calle');   // sin movimiento, Auto vuelve a Enfoque en Calle (si no existe, a la grilla)
  const PRIO = CAMS.findIndex(c => c.label === 'Portón');  // con alguien en Portón, esa cámara manda sobre las demás
  let autoOn = false, model = null, loading = null, run = 0, lastMove = 0, switchedAt = 0;
  const load = src => new Promise((ok, ko) => { const s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = ko; document.head.appendChild(s); });
  async function loadModel() {
    await load('https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js');
    // Brave a veces arranca sin aceleración gráfica (WebGL); el modo cpu es tan lento que no alcanza a detectar, WebAssembly sí
    if (!await tf.setBackend('webgl').catch(() => false)) {
      await load('https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-wasm@4.22.0/dist/tf-backend-wasm.min.js');
      tf.wasm.setWasmPaths('https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-wasm@4.22.0/dist/');
      await tf.setBackend('wasm');
    }
    await load('https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3/dist/coco-ssd.min.js');
    return cocoSsd.load({base: 'mobilenet_v2'});
  }
  const half = document.createElement('canvas'), hx = half.getContext('2d');
  function warn(msg) {
    console.error('[Auto]', msg);
    autoBtn.textContent = 'Auto ⚠'; autoBtn.title = 'El detector falló: ' + msg;
  }
  // De noche las cámaras pasan a infrarrojo: imagen en blanco y negro (los tres colores iguales)
  const tiny = document.createElement('canvas'); tiny.width = 16; tiny.height = 9;
  const tx = tiny.getContext('2d', {willReadFrequently: true});
  function night(v) {
    tx.drawImage(v, 0, 0, 16, 9);
    const d = tx.getImageData(0, 0, 16, 9).data;
    let s = 0;
    for (let k = 0; k < d.length; k += 4) s += Math.abs(d[k] - d[k + 1]) + Math.abs(d[k + 1] - d[k + 2]);
    return s / (d.length / 4) < 4;
  }
  // Miniatura en grises de cada mitad, para comparar con la ronda anterior
  const prev = {}, small = document.createElement('canvas'), gx = small.getContext('2d', {willReadFrequently: true});
  function grayHalf() {
    small.width = 50; small.height = Math.round(50 * half.height / half.width);
    gx.drawImage(half, 0, 0, small.width, small.height);
    const d = gx.getImageData(0, 0, small.width, small.height).data, g = new Uint8Array(d.length / 4);
    for (let k = 0; k < g.length; k++) g[k] = (d[k * 4] + d[k * 4 + 1] + d[k * 4 + 2]) / 3;
    return g;
  }
  function moved(g, p, [bx, by, bw, bh]) {
    if (!p || p.length !== g.length) return false;
    const f = small.width / half.width, W = small.width;
    let ch = 0, tot = 0;
    for (let y = Math.max(0, Math.round(by * f)); y < Math.min(small.height, Math.round((by + bh) * f)); y++)
      for (let x = Math.max(0, Math.round(bx * f)); x < Math.min(W, Math.round((bx + bw) * f)); x++) { tot++; if (Math.abs(g[y * W + x] - p[y * W + x]) > 20) ch++; }
    return tot > 0 && ch / tot >= DET.move;
  }
  async function person(i) {
    const v = tiles[i].querySelector('video');
    if (!v || v.readyState < 2 || !v.videoWidth) return false;
    // Por mitades que se traslapan: el modelo reduce todo a 300×300 y así la persona se ve el doble de grande
    const w = v.videoWidth * 0.6, h = v.videoHeight;
    half.width = 400; half.height = Math.round(400 * h / w);   // algo más que los 300 px del modelo, sin perder detalle
    try {
      const min = night(v) ? DET.night : DET.score, big = 0.6 * half.width * half.height;
      for (const x of [0, v.videoWidth - w]) {
        hx.drawImage(v, x, 0, w, h, 0, 0, half.width, half.height);
        const g = grayHalf(), key = i + ':' + x, p = prev[key];
        prev[key] = g;
        // Un recuadro que cubre casi toda la imagen no es una persona: es la textura de la escena.
        // Y una persona se mueve: si dentro del recuadro nada cambió, es un objeto fijo que el modelo confunde (pasa de noche con el infrarrojo)
        if ((await model.detect(half, 10, min)).some(d => d.class === 'person' && d.bbox[2] * d.bbox[3] < big && moved(g, p, d.bbox))) return true;
      }
    } catch (e) { warn(e.message || String(e)); }
    return false;
  }
  async function detectLoop(id) {
    while (autoOn && id === run) {   // un clic nuevo deja obsoleto este ciclo
      if (!document.hidden) {
        const m = [];
        // Dos detecciones seguidas en la misma cámara, para ignorar aciertos sueltos
        for (let i = 0; i < n; i++) { hits[i] = await person(i) ? hits[i] + 1 : 0; m.push(hits[i] >= 2); tiles[i].classList.toggle('moving', m[i]); }
        if (!autoOn || id !== run) break;
        const now = Date.now(), i = m[PRIO] ? PRIO : m.indexOf(true);
        if (i >= 0) lastMove = now;
        const urgent = i === PRIO && focus !== PRIO;   // Portón entra de inmediato y nadie lo saca mientras haya alguien
        if (i >= 0 && (urgent || (!(focus >= 0 && m[focus]) && (focus < 0 || now - switchedAt > DET.hold)))) { setFocus(i); autoFocus = true; switchedAt = now; }
        else if (autoFocus && now - lastMove > DET.back) setFocus(HOME);
      }
      await new Promise(r => setTimeout(r, DET.pause));
    }
  }
  const autoBtn = $('auto');
  async function setAuto(on) {
    autoOn = on; store.set('cams.m', on ? '1' : '0');
    autoBtn.classList.toggle('on', on); autoBtn.setAttribute('aria-pressed', on);
    hits.fill(0); tiles.forEach(t => t.classList.remove('moving'));
    if (!on) return;
    if (!model) {
      autoBtn.textContent = 'Cargando…';
      try { model = await (loading ||= loadModel()); }
      catch (e) {
        loading = null; autoBtn.textContent = 'Auto'; autoBtn.title = 'No se pudo cargar el detector (¿sin internet?)';
        return setAuto(false);
      }
      autoBtn.textContent = 'Auto';
      if (tf.getBackend() === 'cpu') warn('sin aceleración gráfica ni WebAssembly: demasiado lento');
      if (!autoOn) return;
    }
    detectLoop(++run);
  }
  if (autoBtn) { autoBtn.onclick = () => setAuto(!autoOn); setAuto(store.get('cams.m') === '1'); }

  apply();
}
</script>
</body>
</html>
