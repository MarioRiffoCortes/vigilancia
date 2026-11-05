<?php
// Grabaciones del DVR: calendario lateral, línea de tiempo, reproducción en 1 o 2 pantallas y descarga
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
if (!$sesion) { header('Location: login.php'); exit; }

$camaras = [];
foreach (canales_abiertos() as $c => $_) $camaras[$c] = nombre_canal($c);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#101820">
<title>Vigilancia · Grabaciones</title>
<link rel="manifest" href="manifest.json">
<link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32.png">
<link rel="icon" type="image/svg+xml" href="img/icon.svg">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --ink:#101820;--deck:#16212C;--rail:#243242;--fog:#D9E1E8;--dim:#7E8E9F;--lamp:#F2A93B;--live:#4ADE80;--alarm:#F0565B;--r:12px;
  --cond:"Barlow Condensed","Arial Narrow",system-ui,sans-serif;--sans:"Barlow",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{background:var(--ink);background-image:radial-gradient(120% 80% at 50% -10%,#116fd4 0%,var(--ink) 60%);color:var(--fog);font:15px/1.4 var(--sans);display:flex;flex-direction:column}
button,input,select{font:inherit;color:inherit}
:focus-visible{outline:2px solid var(--lamp);outline-offset:2px}
header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 18px;border-bottom:1px solid var(--rail);background:rgba(22,33,44,.85)}
.brand{display:flex;align-items:center;gap:12px}
.brand svg{color:var(--lamp)}
.brand h1{font:700 22px/1 var(--cond)}
.brand small{display:block;font:500 13px/1.2 var(--sans);color:var(--dim);margin-top:3px}
.seg{display:flex;background:var(--ink);border:1px solid var(--rail);border-radius:10px;padding:3px;gap:2px}
.seg button,.seg a{border:0;background:transparent;color:var(--dim);font:600 15px/1 var(--cond);letter-spacing:.03em;padding:7px 12px;border-radius:7px;cursor:pointer;text-decoration:none}
.seg button:hover,.seg a:hover{color:var(--fog)}
.seg button.on{background:var(--lamp);color:#1B1305}
.seg button:disabled{opacity:.4;cursor:default}

.wrap{flex:1;display:grid;grid-template-columns:300px minmax(0,1fr);min-height:0}
aside{border-right:1px solid var(--rail);background:rgba(22,33,44,.6);padding:14px;overflow:auto;display:flex;flex-direction:column;gap:14px}
.box{background:var(--deck);border:1px solid var(--rail);border-radius:var(--r);padding:12px}
.box h2{font:700 17px/1 var(--cond);letter-spacing:.02em;margin-bottom:10px;color:var(--fog)}
[hidden]{display:none !important}
.box .seg button{flex:1}   /* 1 y 2 pantallas reparten el ancho, sin hueco a la derecha */
.btn{border:1px solid var(--lamp);background:var(--lamp);color:#1B1305;font:600 15px/1 var(--cond);letter-spacing:.03em;padding:8px 12px;border-radius:8px;cursor:pointer}
.btn.ghost{background:transparent;color:var(--lamp)}
.btn:disabled{opacity:.45;cursor:default}

/* Calendario */
.cal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.cal-head b{font:700 17px/1 var(--cond);text-transform:capitalize}
.cal-head button{width:30px;height:30px;border-radius:8px;border:1px solid var(--rail);background:var(--ink);cursor:pointer}
.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;text-align:center}
.cal span{font:600 12px/1 var(--cond);color:var(--dim);padding:4px 0}
.cal button{aspect-ratio:1;border:1px solid transparent;border-radius:8px;background:transparent;color:var(--dim);font:600 15px/1 var(--cond);cursor:default;position:relative}
.cal button.rec{color:var(--fog);background:rgba(242,169,59,.14);cursor:pointer}
.cal button.rec::after{content:"";position:absolute;left:50%;bottom:4px;width:4px;height:4px;margin-left:-2px;border-radius:50%;background:var(--lamp)}
.cal button.rec:hover{border-color:var(--lamp)}
.cal button.sel{background:var(--lamp);color:#1B1305}
.cal button.sel::after{background:#1B1305}
.cal button.today{border-color:var(--rail)}
.legend{font-size:12px;color:var(--dim);margin-top:8px}
.legend i{display:inline-block;width:10px;height:10px;border-radius:3px;background:rgba(242,169,59,.35);vertical-align:-1px;margin-right:5px}

.field{display:grid;gap:5px;margin-top:10px}
.field label{font-size:13px;color:var(--dim)}
.field select,.field input{background:var(--ink);border:1px solid var(--rail);border-radius:8px;padding:8px 9px;width:100%}
.row{display:grid;grid-template-columns:1fr 1fr;gap:8px}

/* Reproductor */
main{display:flex;flex-direction:column;gap:10px;padding:12px;min-width:0;min-height:0}
.screens{flex:1;min-height:200px;display:grid;gap:4px;grid-template-columns:1fr;background:#05080B;border:1px solid var(--rail);border-radius:var(--r);overflow:hidden;position:relative}
.screens.two{grid-template-columns:1fr 1fr}
.scr{position:relative;min-width:0;min-height:0;display:grid;place-items:center;container-type:size}
/* Siempre 16:9, como en el vivo: las cámaras 3 y 4 graban 944×1080 (imagen comprimida a lo ancho) */
.scr video{width:min(100cqw,calc(100cqh * 16 / 9));height:min(100cqh,calc(100cqw * 9 / 16));object-fit:fill;background:#05080B}
.scr .tag{position:absolute;left:10px;top:8px;font:700 18px/1 var(--cond);text-shadow:0 1px 2px #000;pointer-events:none}
.screens:not(.two) .scr.b{display:none}
.msg{position:absolute;inset:0;display:grid;place-items:center;text-align:center;padding:20px;color:var(--dim);pointer-events:none}
.msg b{display:block;color:var(--fog);font:700 22px/1.2 var(--cond);margin-bottom:6px}

.controls{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--deck);border:1px solid var(--rail);border-radius:var(--r);padding:8px 10px}
.controls .ico{width:38px;height:38px;border-radius:9px;border:1px solid var(--rail);background:var(--ink);display:grid;place-items:center;cursor:pointer}
.controls .ico:hover{border-color:var(--lamp);color:var(--lamp)}
.controls .ico svg{width:18px;height:18px}
.clock{font:600 22px/1 var(--cond);font-variant-numeric:tabular-nums;min-width:90px}
.seek{flex:1;min-width:140px;accent-color:var(--lamp)}

/* Pantalla completa: video arriba, barra de control y línea de tiempo abajo */
main:fullscreen,main.full{background:var(--ink);padding:10px;gap:8px}
main:fullscreen .screens,main.full .screens{flex:1;aspect-ratio:auto;border-radius:8px}
main.full{position:fixed;inset:0;z-index:50}   /* iPhone: Safari no permite pantalla completa fuera del video */
.timeline{background:var(--deck);border:1px solid var(--rail);border-radius:var(--r);padding:10px 12px 8px}
.tl-row{display:grid;grid-template-columns:90px 1fr;align-items:center;gap:10px;margin-bottom:6px}
.tl-row b{font:600 15px/1 var(--cond);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.track{position:relative;height:22px;background:var(--ink);border:1px solid var(--rail);border-radius:6px;cursor:crosshair;overflow:hidden}
.track .seg-rec{position:absolute;top:0;bottom:0;background:rgba(242,169,59,.45)}
.track .win{position:absolute;top:0;bottom:0;background:rgba(74,222,128,.35);border-left:2px solid var(--live)}
.track .cur{position:absolute;top:-2px;bottom:-2px;width:2px;background:#fff}
.hours{position:relative;height:14px;margin-left:100px;font:500 11px/1 var(--sans);color:var(--dim)}
.hours span{position:absolute;transform:translateX(-50%)}
.tip{position:fixed;z-index:9;pointer-events:none;background:var(--ink);border:1px solid var(--lamp);border-radius:6px;padding:3px 7px;font:600 14px/1 var(--cond);display:none}

@media (max-width:860px){
  .wrap{grid-template-columns:1fr;overflow:auto}
  aside{border-right:0;border-bottom:1px solid var(--rail);overflow:visible}
  .screens{flex:none;aspect-ratio:16/9}
  .screens.two{aspect-ratio:32/9}
  .brand small{display:none}
}
@media (max-width:520px){ header{padding:10px 12px} .tl-row{grid-template-columns:60px 1fr} .hours{margin-left:70px} }
</style>
</head>
<body>
<header>
  <div class="brand">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7.5 15.5 4l1.6 5.6L4.6 13z"/><path d="m17.1 9.6 3.4-1"/><path d="M7 12.6 8.2 17H4"/><circle cx="12.2" cy="8" r="1.2"/></svg>
    <div><h1>Grabaciones</h1><small>Disco del DVR</small></div>
  </div>
  <div class="seg"><a href="./">En vivo</a><a href="login.php?salir">Salir</a></div>
</header>

<div class="wrap">
<aside>
  <section class="box" aria-labelledby="hCal">
    <h2 id="hCal">Calendario</h2>
    <div class="cal-head">
      <button id="mPrev" aria-label="Mes anterior">‹</button><b id="mName"></b><button id="mNext" aria-label="Mes siguiente">›</button>
    </div>
    <div class="cal" id="cal"></div>
    <div class="legend"><i></i>Días con grabación <span id="calState"></span></div>
  </section>

  <section class="box" aria-labelledby="hVer">
    <h2 id="hVer">Ver</h2>
    <div class="seg" role="group" aria-label="Pantallas">
      <button id="m1" class="on">1 pantalla</button><button id="m2">2 pantallas</button>
    </div>
    <div class="row">
      <div class="field"><label for="chA">Cámara</label><select id="chA"></select></div>
      <div class="field" id="fB" hidden><label for="chB">2.ª cámara</label><select id="chB"></select></div>
    </div>
    <div class="row">
      <div class="field"><label for="tFrom">Desde (hora exacta)</label><input id="tFrom" type="time" step="1" value="00:00:00"></div>
      <div class="field"><label for="tMin">Duración</label>
        <select id="tMin"><option value="1">1 min</option><option value="5" selected>5 min</option><option value="10">10 min</option><option value="15">15 min</option><option value="30">30 min</option><option value="60">1 hora</option></select></div>
    </div>
    <button class="btn" id="load" style="width:100%;margin-top:12px">Reproducir</button>
  </section>
</aside>

<main>
  <div class="screens" id="screens">
    <!-- muted: las grabaciones no traen audio, y así Brave/Chrome dejan que partan solas cuando llega el video del DVR -->
    <div class="scr a"><video id="vA" playsinline preload="auto" muted></video><span class="tag" id="tagA"></span></div>
    <div class="scr b"><video id="vB" playsinline preload="auto" muted></video><span class="tag" id="tagB"></span></div>
    <div class="msg" id="msg"><div><b>Elige un día y una hora</b>Marca un punto en la línea de tiempo o escribe la hora exacta y pulsa «Reproducir».</div></div>
  </div>

  <div class="controls">
    <button class="ico" id="back" title="Tramo anterior" aria-label="Tramo anterior"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 17 6 12l5-5M18 17l-5-5 5-5"/></svg></button>
    <button class="ico" id="play" title="Reproducir / pausa (espacio)" aria-label="Reproducir o pausar"></button>
    <button class="ico" id="fwd" title="Tramo siguiente" aria-label="Tramo siguiente"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m13 17 5-5-5-5M6 17l5-5-5-5"/></svg></button>
    <span class="clock" id="clock">--:--:--</span>
    <input class="seek" id="seek" type="range" min="0" max="1000" value="0" aria-label="Posición en el tramo">
    <div class="seg" id="speed" role="group" aria-label="Velocidad">
      <button data-r="0.5">½×</button><button data-r="1" class="on">1×</button><button data-r="2">2×</button><button data-r="4">4×</button><button data-r="8">8×</button><button data-r="16">16×</button>
    </div>
    <button class="btn ghost" id="dl" disabled title="Descargar el tramo cargado en MP4">Descargar</button>
    <button class="ico" id="full" title="Pantalla completa (doble clic en el video)" aria-label="Pantalla completa"></button>
  </div>

  <section class="timeline" aria-label="Línea de tiempo del día">
    <div id="tracks"></div>
    <div class="hours" id="hours"></div>
  </section>
  <div class="tip" id="tip"></div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/jmuxer@2.0.5/dist/jmuxer.min.js"></script>
<script>
const CAMS = <?= json_encode($camaras, JSON_UNESCAPED_UNICODE) ?>;
const $ = id => document.getElementById(id);
const api = async (a, params = {}) => {
  const r = await fetch('api.php?a=' + a + '&' + new URLSearchParams(params), {cache: 'no-store'});
  if (r.status === 401) location.href = 'login.php';
  const d = await r.json().catch(() => ({error: 'Respuesta inválida del servidor'}));
  if (!r.ok || d.error) throw new Error(d.error || 'Error ' + r.status);
  return d;
};
const pad = n => String(n).padStart(2, '0');
const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const sec = t => { const [h, m, s = 0] = t.split(':').map(Number); return h * 3600 + m * 60 + s; };
const hms = s => `${pad(Math.floor(s / 3600))}:${pad(Math.floor(s % 3600 / 60))}:${pad(Math.floor(s % 60))}`;
const store = { get(k){ try { return localStorage.getItem(k); } catch(e){ return null; } }, set(k,v){ try { localStorage.setItem(k,v); } catch(e){} } };

const today = ymd(new Date());
const S = {month: today.slice(0, 7), date: today, two: false, files: {}, days: {}, clip: null};

const openChannels = () => Object.keys(CAMS).map(Number);
function fillSelects() {
  const chs = openChannels();
  for (const id of ['chA', 'chB']) {
    const sel = $(id), prev = sel.value;
    sel.innerHTML = chs.map(c => `<option value="${c}">Cámara ${c + 1} · ${CAMS[c]}</option>`).join('');
    if (prev !== '' && chs.includes(+prev)) sel.value = prev;
  }
  if (chs.length > 1 && $('chB').value === $('chA').value) $('chB').value = chs.find(c => c !== +$('chA').value);
  $('m2').disabled = chs.length < 2;
  if (chs.length < 2) setMode(false);
  $('load').disabled = !chs.length;
}
function selected() { return S.two ? [+$('chA').value, +$('chB').value] : [+$('chA').value]; }

// ---------- Calendario ----------
const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
function drawCal() {
  const [y, m] = S.month.split('-').map(Number);
  $('mName').textContent = `${MESES[m - 1]} ${y}`;
  $('mNext').disabled = S.month >= today.slice(0, 7);
  const cal = $('cal');
  cal.innerHTML = ['L','M','M','J','V','S','D'].map(d => `<span>${d}</span>`).join('');
  const first = (new Date(y, m - 1, 1).getDay() + 6) % 7, n = new Date(y, m, 0).getDate();
  for (let i = 0; i < first; i++) cal.appendChild(document.createElement('i'));
  for (let d = 1; d <= n; d++) {
    const date = `${S.month}-${pad(d)}`, b = document.createElement('button');
    b.textContent = d;
    b.className = (S.days[date] ? 'rec' : '') + (date === S.date ? ' sel' : '') + (date === today ? ' today' : '');
    b.disabled = !S.days[date];
    b.setAttribute('aria-label', `${d} de ${MESES[m - 1]}${S.days[date] ? ', con grabación' : ''}`);
    b.onclick = () => { S.date = date; drawCal(); loadFiles(); };
    cal.appendChild(b);
  }
}
let calRun = 0;
async function loadDays() {
  const chs = selected(), run = ++calRun;
  $('calState').textContent = '· consultando el DVR…';
  drawCal();
  try {
    const d = await api('days', {month: S.month, ch: chs.join(',')});
    if (run !== calRun) return;
    S.days = d.days; $('calState').textContent = '';
  } catch (e) { if (run === calRun) $('calState').textContent = '· ' + e.message; }
  drawCal();
}
$('mPrev').onclick = () => { const [y, m] = S.month.split('-').map(Number); S.month = ymd(new Date(y, m - 2, 1)).slice(0, 7); loadDays(); };
$('mNext').onclick = () => { const [y, m] = S.month.split('-').map(Number); S.month = ymd(new Date(y, m, 1)).slice(0, 7); loadDays(); };

// ---------- Línea de tiempo del día ----------
$('hours').innerHTML = [0, 3, 6, 9, 12, 15, 18, 21, 24].map(h => `<span style="left:${h / 24 * 100}%">${pad(h)}:00</span>`).join('');
async function loadFiles() {
  const chs = selected();
  S.files = {};
  drawTracks();
  if (!openChannels().length) return;
  try {
    S.files = (await api('files', {date: S.date, ch: chs.join(',')})).files;
    // Primera vez en el día: parte en el primer tramo grabado
    const first = chs.map(c => S.files[c]?.[0]?.[0]).filter(Boolean).sort()[0];
    if (first && !S.clip && sec($('tFrom').value) < sec(first)) $('tFrom').value = first;
  } catch (e) { showMsg('No se pudo leer el día', e.message); }
  drawTracks();
}
function drawTracks() {
  const from = sec($('tFrom').value || '00:00:00'), len = +$('tMin').value * 60;
  $('tracks').innerHTML = selected().map(c => `<div class="tl-row"><b>${CAMS[c]}</b><div class="track" data-ch="${c}">`
    + (S.files[c] || []).map(([a, b]) => `<i class="seg-rec" style="left:${sec(a) / 864}%;width:${Math.max(.15, (sec(b) - sec(a)) / 864)}%"></i>`).join('')
    + `<i class="win" style="left:${from / 864}%;width:${Math.max(.3, len / 864)}%"></i><i class="cur" id="cur${c}" hidden></i></div></div>`).join('');
  document.querySelectorAll('.track').forEach(t => {
    const at = e => Math.max(0, Math.min(86399, Math.round((e.clientX - t.getBoundingClientRect().left) / t.clientWidth * 86400)));
    t.onmousemove = e => { const tip = $('tip'); tip.style.display = 'block'; tip.textContent = hms(at(e)); tip.style.left = e.clientX + 12 + 'px'; tip.style.top = e.clientY - 30 + 'px'; };
    t.onmouseleave = () => $('tip').style.display = 'none';
    t.onclick = e => { $('tFrom').value = hms(at(e)); drawTracks(); load(); };
  });
  updateCursor();
}
$('tFrom').onchange = drawTracks;
$('tMin').onchange = drawTracks;

// ---------- Reproducción ----------
const vA = $('vA'), vB = $('vB');
function showMsg(title, text = '') {
  $('msg').hidden = !title;
  if (title) $('msg').innerHTML = `<div><b>${title}</b>${text}</div>`;
}
function setMode(two) {
  S.two = two;
  $('m1').classList.toggle('on', !two); $('m2').classList.toggle('on', two);
  $('fB').hidden = !two;
  $('screens').classList.toggle('two', two);
}
$('m1').onclick = () => { setMode(false); refresh(); };
$('m2').onclick = () => { setMode(true); refresh(); };
$('chA').onchange = $('chB').onchange = () => refresh();
function refresh() { store.set('rec.sel', JSON.stringify({a: $('chA').value, b: $('chB').value, two: S.two})); loadDays(); loadFiles(); }

// Reproducción inmediata: el video parte mientras el DVR lo va entregando.
// El DVR manda H.264 con una cabecera propia por cuadro (00 00 01 FC = 8 bytes, FD = 16 bytes con los cuadros por segundo);
// aquí se quitan y jmuxer arma el video en el navegador.
const inRec = (ch, t) => (S.files[ch] || []).some(([a, b]) => sec(a) <= t && t < sec(b));
const has = (v, t) => [...Array(v.buffered.length).keys()].some(i => v.buffered.start(i) <= t && t < v.buffered.end(i) - .3);
let ctl = null, muxers = [];
// Si aun así el navegador bloquea que parta solo, se avisa en vez de quedar en «Conectando»
const blocked = e => { if (e.name === 'NotAllowedError') showMsg('Grabación lista', 'Pulsa ▶ para reproducir.'); };
async function stream(v, i, params, signal, clip) {
  const r = await fetch('api.php?a=stream&' + new URLSearchParams(params), {signal, cache: 'no-store'});
  if (r.status === 401) location.href = 'login.php';
  if ((r.headers.get('content-type') || '').includes('json')) {
    const d = await r.json();
    if (d.error) throw new Error(d.error);
    v.src = 'api.php?a=video&key=' + d.key;   // ya estaba completo en caché: MP4 con saltos libres
    clip.done[i] = true;
    v.playbackRate = rate;
    return v.play().catch(blocked);
  }
  const mux = muxers[i] = new JMuxer({node: v, mode: 'video', flushingTime: 100, fps: 16, debug: false});
  v.playbackRate = rate;
  v.play().catch(blocked);
  const rd = r.body.getReader(), u32 = (b, p) => (b[p] | b[p + 1] << 8 | b[p + 2] << 16 | b[p + 3] << 24) >>> 0;
  let buf = new Uint8Array(0), fps = 16, n = 0;
  for (;;) {
    const {done, value} = await rd.read();
    if (done) break;
    const nb = new Uint8Array(buf.length + value.length); nb.set(buf); nb.set(value, buf.length); buf = nb;
    let p = 0;
    while (buf.length - p >= 16) {
      if (buf[p] || buf[p + 1] || buf[p + 2] !== 1 || buf[p + 3] < 0xF9) { p++; continue; }   // se resincroniza en la próxima cabecera
      const t = buf[p + 3], hl = t === 0xFD ? 16 : 8;
      const len = t === 0xFD ? u32(buf, p + 12) : t === 0xFC ? u32(buf, p + 4) : buf[p + 6] | buf[p + 7] << 8;
      if (buf.length - p < hl + len) break;
      if (t === 0xFD) {
        fps = (buf[p + 5] & 0x3F) || fps;
        // Hora real del primer cuadro completo (el DVR parte en el cuadro clave más cercano): seg:6 min:6 hora:5 …
        const ts = u32(buf, p + 8);
        if (!n) clip.starts[i] = (ts >> 12 & 31) * 3600 + (ts >> 6 & 63) * 60 + (ts & 63);
        if (!n && !i) clip.start = clip.starts[0];
      }
      if (t === 0xFD || t === 0xFC) {
        // Duración exacta acumulada: 1000/16 no es entero y el reloj se correría
        mux.feed({video: buf.slice(p + hl, p + hl + len), duration: Math.round((n + 1) * 1000 / fps) - Math.round(n * 1000 / fps)});
        n++;
      }
      p += hl + len;
    }
    buf = buf.slice(p);
  }
  if (!n && clip === S.clip) throw new Error(`${CAMS[params.ch]}: el DVR no entregó video de ese tramo. Prueba de nuevo.`);
  clip.done[i] = true;
}
// at = segundo dentro del tramo actual (saltos de la barra de avance): se pide desde ahí, pero el tramo
// (inicio y duración) sigue siendo el mismo, así la barra queda donde se soltó
function load(at) {
  const keep = typeof at === 'number' && S.clip, chs = keep ? S.clip.chs : selected();
  const base = keep ? S.clip.base : sec($('tFrom').value || '00:00'), len = keep ? S.clip.len : $('tMin').value * 60;   // el campo omite los segundos cuando son :00
  const fromS = keep ? Math.min(86399, base + Math.floor(at)) : base, from = hms(fromS);
  const min = keep ? Math.max(1, Math.ceil((len - at) / 60)) : len / 60;
  if (S.two && chs[0] === chs[1]) return showMsg('Elige dos cámaras distintas');
  if (S.files[chs[0]] && !inRec(chs[0], sec(from))) return showMsg('Sin grabación a esa hora', `${CAMS[chs[0]]} no grabó a las ${from}. Elige un tramo marcado en la línea de tiempo.`);
  // Suelta el tramo anterior: con el turno nuevo el servidor deja de pedirlo al DVR
  ctl?.abort();
  ctl = new AbortController();
  muxers.forEach(m => { try { m.destroy(); } catch (e) {} });
  muxers = [];
  [vA, vB].forEach(v => { v.removeAttribute('src'); v.load(); });
  S.clip = {chs, base, len, start: fromS, starts: [fromS, fromS], date: keep ? S.clip.date : S.date, done: []};
  showMsg('Conectando con el DVR…', `${CAMS[chs[0]]} · ${S.date} · ${from}`);
  const tok = Date.now().toString(36) + Math.random().toString(36).slice(2, 8), signal = ctl.signal, clip = S.clip;
  // Ambas pantallas a la vez: el servidor pide la 2.ª con otro usuario del DVR si la 1.ª ya ocupa el suyo
  const fail = e => { if (e.name !== 'AbortError' && clip === S.clip) showMsg('No se pudo reproducir', e.message); };
  stream(vA, 0, {ch: chs[0], date: clip.date, from, min, tok}, signal, clip).catch(fail);
  if (chs[1] !== undefined) stream(vB, 1, {ch: chs[1], with: chs[0], date: clip.date, from, min, tok}, signal, clip).catch(fail);
  $('tagA').textContent = CAMS[chs[0]]; $('tagB').textContent = chs[1] !== undefined ? CAMS[chs[1]] + ' · esperando al DVR…' : '';
  $('dl').disabled = false;
}
$('load').onclick = () => load();
vA.addEventListener('playing', () => showMsg(''));

// Segunda pantalla: el DVR entrega una cámara a la vez, así que se suma cuando su video alcanza a la primera
// Segundos de video ya recibidos por delante de t
const ahead = (v, t) => { for (let i = 0; i < v.buffered.length; i++) if (v.buffered.start(i) <= t + .1 && t < v.buffered.end(i)) return v.buffered.end(i) - t; return 0; };
setInterval(() => {
  const c = S.clip;
  if (!c) return;
  const two = S.two && c.chs.length > 1;
  // Fin del tramo recibido en vivo (sin MP4 completo no hay evento 'ended'; el último cuadro queda sin mostrar):
  // llegaron ambas y el video dejó de avanzar a menos de 1 s del final => sigue con el siguiente
  const stuck = c.lastT === vA.currentTime;
  c.lastT = vA.currentTime;
  if (c.done[0] && (!two || c.done[1]) && !vA.paused && stuck && vA.buffered.length && vA.currentTime >= vA.buffered.end(vA.buffered.length - 1) - 1) return jump(1);
  if (!vA.paused && pos() >= c.len) return jump(1);   // tras un salto se pide en minutos enteros: se corta al final del tramo
  if (!two) return;
  // Avanzan juntas: si a una le falta video (a velocidad alta el DVR no alcanza a entregar), ambas esperan
  const tB = vA.currentTime + c.starts[0] - c.starts[1];   // misma hora real en ambas
  // Cada cámara parte en su propio cuadro clave: si la 2.ª empieza un poco después en la hora real,
  // espera quieta en su primer cuadro hasta que llegue su momento, sin frenar a la 1.ª
  const early = vB.buffered.length > 0 && tB < vB.buffered.start(0) - .1;
  const shortA = !c.done[0] && ahead(vA, vA.currentTime) < .3, shortB = !early && !c.done[1] && ahead(vB, tB) < .3;
  const readyA = c.done[0] || ahead(vA, vA.currentTime) >= 2, readyB = early || c.done[1] || ahead(vB, tB) >= 2;
  if (!c.wait && (shortA || shortB) && !vA.paused) { c.wait = true; vA.pause(); }
  if (c.wait && readyA && readyB) { c.wait = false; vA.play().catch(() => {}); }
  $('tagA').textContent = CAMS[c.chs[0]] + (c.wait && !readyA ? ' · esperando al DVR…' : '');
  $('tagB').textContent = CAMS[c.chs[1]] + (!early && (c.wait && !readyB || !ahead(vB, tB) && !c.done[1]) ? ' · esperando al DVR…' : '');
  if (c.wait || early) return vB.pause();
  if (ahead(vB, tB) > 0 && Math.abs(vB.currentTime - tB) > .3) vB.currentTime = tB;
  if (vA.paused !== vB.paused) vA.paused ? vB.pause() : vB.play().catch(() => {});
}, 250);

const PLAY = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>', PAUSE = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 5h4v14H7zM13 5h4v14h-4z"/></svg>';
$('play').innerHTML = PLAY;
vA.addEventListener('play', () => $('play').innerHTML = PAUSE);
vA.addEventListener('pause', () => $('play').innerHTML = PLAY);
$('play').onclick = () => {
  if (!S.clip) return load();
  if (S.clip.wait) { S.clip.wait = false; return; }   // pausa durante la espera: queda en pausa
  vA.paused ? vA.play() : vA.pause();
};
document.addEventListener('keydown', e => { if (e.code === 'Space' && !/INPUT|SELECT|BUTTON/.test(e.target.tagName)) { e.preventDefault(); $('play').click(); } });

let rate = 1;
document.querySelectorAll('#speed button').forEach(b => b.onclick = () => {
  rate = +b.dataset.r;
  vA.playbackRate = vB.playbackRate = rate;
  document.querySelectorAll('#speed button').forEach(x => x.classList.toggle('on', x === b));
});

// Posición dentro del tramo (0 … len s): el video puede haber partido más adelante tras un salto
const pos = () => S.clip.start - S.clip.base + (vA.currentTime || 0);
function updateCursor() {
  if (!S.clip) return;
  const t = S.clip.start + (vA.currentTime || 0);
  if (!dragging) {   // mientras arrastra, la barra y el reloj muestran adónde va a saltar
    $('clock').textContent = hms(t);
    $('seek').value = pos() / S.clip.len * 1000;
  }
  S.clip.chs.forEach(c => { const el = $('cur' + c); if (el && S.clip.date === S.date) { el.hidden = false; el.style.left = t / 864 + '%'; } });
}
vA.ontimeupdate = updateCursor;
// Saltar: dentro de lo ya recibido es instantáneo; si aún no llega, se pide al DVR desde ese segundo, en el mismo tramo
let dragging = false;
$('seek').addEventListener('pointerdown', () => dragging = true);
$('seek').addEventListener('pointerup', () => setTimeout(() => dragging = false));
$('seek').oninput = () => {
  dragging = true;
  if (S.clip) $('clock').textContent = hms(S.clip.base + $('seek').value / 1000 * S.clip.len);
};
$('seek').onchange = () => {
  dragging = false;
  if (!S.clip) return;
  const t = $('seek').value / 1000 * S.clip.len, local = t - (S.clip.start - S.clip.base);
  if (local >= 0 && has(vA, local)) vA.currentTime = local;
  else load(t);
};
const jump = dir => { const s = Math.max(0, Math.min(86399, sec($('tFrom').value) + dir * $('tMin').value * 60)); $('tFrom').value = hms(s); drawTracks(); load(); };
$('back').onclick = () => jump(-1);
$('fwd').onclick = () => jump(1);
vA.onended = () => { if (S.clip) jump(1); };   // al terminar, sigue con el tramo siguiente

// Pantalla completa: botón o doble clic en el video; incluye la barra de control y la línea de tiempo
const FULL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>',
      EXIT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>';
const main = document.querySelector('main');
const fsEl = () => document.fullscreenElement || document.webkitFullscreenElement || main.classList.contains('full');
function toggleFull() {
  if (main.classList.contains('full')) { main.classList.remove('full'); return fullIcon(); }
  if (fsEl()) return (document.exitFullscreen || document.webkitExitFullscreen).call(document);
  const req = main.requestFullscreen || main.webkitRequestFullscreen;
  if (req) req.call(main);
  else { main.classList.add('full'); fullIcon(); }   // sin API: ocupa toda la ventana
}
document.addEventListener('keydown', e => { if (e.key === 'Escape' && main.classList.contains('full')) toggleFull(); });
const fullIcon = () => { $('full').innerHTML = fsEl() ? EXIT : FULL; $('full').title = fsEl() ? 'Salir de pantalla completa (Esc)' : 'Pantalla completa (doble clic en el video)'; };
fullIcon();
document.addEventListener('fullscreenchange', fullIcon);
document.addEventListener('webkitfullscreenchange', fullIcon);
$('full').onclick = toggleFull;
$('screens').ondblclick = toggleFull;

$('dl').onclick = async () => {
  if (!S.clip) return;
  const {chs, base, len, date} = S.clip, from = hms(base), min = len / 60;   // siempre el tramo completo, aunque se haya saltado
  showMsg('Preparando la descarga', chs.length > 1 ? 'Se unen ambas cámaras lado a lado; puede tardar un poco.' : 'Terminando de recibir el tramo del DVR…');
  // Si el tramo ya llegó entero, esto responde al instante
  try { for (const ch of chs) await api('clip', {ch, date, from, min}); }
  catch (e) { return showMsg('No se pudo preparar la descarga', e.message); }
  location.href = 'api.php?a=download&key=' + chs.map(c => `${c}_${date}_${from.replace(/:/g, '')}_${min}`).join(',');
  setTimeout(() => showMsg(''), 3000);
};

// Inicio
try { const s = JSON.parse(store.get('rec.sel') || '{}'); fillSelects(); if (s.a) $('chA').value = s.a; if (s.b) $('chB').value = s.b; if (s.two && !$('m2').disabled) setMode(true); } catch (e) {}
fillSelects();
loadDays(); loadFiles();
</script>
</body>
</html>
