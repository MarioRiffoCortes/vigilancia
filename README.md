# 📹 Vigilancia · Visor web para DVR

Portal web para ver en el navegador (PC o celular) las cámaras de un **DVR/XVR tipo XMeye / Xiongmai**
(los que se usan con la app **XVR Pro**), en vivo y también sus **grabaciones**, sin depender de la app del fabricante.

Funciona sobre un PC con Windows y [Laragon](https://laragon.org/) (Apache + PHP). El video en vivo lo entrega
[go2rtc](https://github.com/AlexxIT/go2rtc) y las grabaciones se leen directo del disco del DVR por su protocolo binario (puerto 34567).
Se puede instalar como aplicación (PWA) en Android y verse desde fuera de casa con [Tailscale](https://tailscale.com/).

---

## ✨ Funciones

### En vivo (`index.php`)
- **Grilla** con todas las cámaras, ajustada a 16:9 sin franjas negras.
- **Enfoque**: una cámara grande y las demás al costado, todas siguen en vivo (no se reconectan al cambiar de vista).
- Calidad **SD / HD / UHD** (UHD prioriza WebRTC para menos retraso).
- Estado real de cada cámara: *En vivo*, *Conectando* o *Sin señal*, y contador de cámaras en línea.
- Atajos: clic = enfocar, doble clic = pantalla completa, ← → para cambiar, `Esc` para volver.
  En el celular: deslizar para cambiar de cámara y el botón *atrás* vuelve a la grilla.
- **Ficha técnica** en el celular: resolución, bitrate, FPS, códec, retraso, cuadros perdidos y audio.
- **Modo Auto (detector de personas)**: con TensorFlow.js + COCO‑SSD, en el navegador, pone en Enfoque la cámara donde aparece
  alguien. Ignora ramas, sombras y autos, sube la exigencia de noche (infrarrojo) y da prioridad a una cámara elegida.
- **Instalable como app** (manifest + service worker) con pantalla de “sin conexión”.

### Grabaciones (`grabaciones.php`, requiere inicio de sesión)
- **Calendario** con los días que tienen grabación.
- **Línea de tiempo** del día por cámara: clic en un punto y el video parte en 1–2 s mientras llega del DVR.
- **1 o 2 pantallas** sincronizadas a la misma hora real.
- Velocidad ½× a 16×, avance por tramos, barra de posición y pantalla completa.
- **Descarga en MP4** (con 2 pantallas, ambas cámaras lado a lado en un solo video).
- Caché de índices y clips (los clips se borran solos a las 24 h).

---

## 🧱 Cómo funciona

```
 Navegador (PC / celular / app instalada)
        │  HTTP (Apache + PHP)          │  WebSocket :1984 (video en vivo)
        ▼                               ▼
   index.php / grabaciones.php      go2rtc ──RTSP :554──► DVR
        │
     api.php ──► dvr.php ──NetSDK :34567──► DVR (búsqueda y descarga de grabaciones)
        │
     ffmpeg (convierte el H.264 del DVR a MP4)
```

| Archivo | Para qué sirve |
|---|---|
| `index.php` | Vista en vivo: grilla, enfoque, calidad, ficha técnica, detector Auto |
| `grabaciones.php` | Calendario, línea de tiempo, reproductor y descargas |
| `api.php` | API de grabaciones: días, tramos, video inmediato, clips y descargas |
| `dvr.php` | Cliente del protocolo binario del DVR (login, búsqueda, descarga) |
| `info.php` | Datos técnicos de un flujo (sin exponer la URL ni la clave del DVR) |
| `login.php` / `auth.php` | Inicio de sesión para ver grabaciones |
| `config.php` | Nombres de cámaras, IP, usuarios y clave del DVR, rutas |
| `go2rtc/go2rtc.yaml` | Flujos RTSP de cada cámara para el vivo |
| `Camaras ON-OFF.bat` | Enciende/apaga go2rtc (y Apache si hace falta) y abre el portal |
| `iniciar.bat` / `detener.bat` | Iniciar o detener solo go2rtc |
| `js/video-rtc.js`, `js/video-stream.js` | Reproductor de go2rtc (WebRTC / MSE / HLS / MJPEG) |
| `manifest.json`, `sw.js`, `img/` | App instalable e íconos |

---

## 📋 Requisitos

- Windows con **[Laragon](https://laragon.org/)** (Apache + **PHP 8.1 o superior**).
- **[go2rtc](https://github.com/AlexxIT/go2rtc/releases)** → `go2rtc.exe` dentro de `go2rtc/`.
- **[ffmpeg](https://www.gyan.dev/ffmpeg/builds/)** → `ffmpeg.exe` dentro de `ffmpeg/` (solo para grabaciones).
- Un DVR/XVR compatible con XMeye / Xiongmai (RTSP en el puerto 554 y NetSDK en el 34567).
- Opcional: **Tailscale** en el PC y en el celular para verlo fuera de casa.

> Los binarios `go2rtc.exe` y `ffmpeg.exe` **no vienen en el repositorio**: se descargan aparte.

---

## 🚀 Instalación

1. Clona o descarga el proyecto en la carpeta web de Laragon:
   ```
   C:\laragon\www\vigilancia
   ```
2. Descarga `go2rtc.exe` en `vigilancia\go2rtc\` y `ffmpeg.exe` en `vigilancia\ffmpeg\`.
3. Crea tu configuración a partir de los ejemplos:
   ```
   copy config.example.php config.php
   copy go2rtc\go2rtc.example.yaml go2rtc\go2rtc.yaml
   ```
4. Edita **`go2rtc/go2rtc.yaml`**: IP del DVR, usuario y clave, y deja solo los canales que tengas.
   Si la clave tiene símbolos, escríbelos codificados (`@` = `%40`, `:` = `%3A`, `#` = `%23`, etc.).
5. Edita **`config.php`**: nombres de las cámaras, IP, puerto, usuarios del DVR, clave y carpeta de caché.
6. Con Laragon iniciado, abre **`Camaras ON-OFF.bat`** (o `iniciar.bat`).
   La primera vez Windows pedirá permiso de firewall: acepta para **redes privadas**.
7. Se abre `http://localhost/vigilancia/`.

Desde otro equipo de la casa: `http://IP-DEL-PC/vigilancia/`

### Usuarios del DVR
El DVR acepta **una sola sesión por usuario** y entrega **una grabación a la vez por usuario**. Por eso se usan tres usuarios
(todos con la misma clave):

| Usuario | Uso |
|---|---|
| `usuario_vivo` | Video en vivo (en `go2rtc.yaml`), calendario y línea de tiempo, e inicio de sesión del portal |
| `usuario_rec1` | Reproducir grabaciones de las cámaras 1 y 2 |
| `usuario_rec2` | Reproducir grabaciones de las cámaras 3 y 4 |

Créalos en el menú de usuarios del DVR y pon sus nombres en `config.php`. Con 2 pantallas, si ambas cámaras corresponden al mismo
usuario, la segunda se pide automáticamente con el otro.

### Ver desde el celular fuera de casa
1. Instala Tailscale en el PC y en el celular, con la misma cuenta.
2. Abre `http://NOMBRE-O-IP-TAILSCALE-DEL-PC/vigilancia/`.
3. En Chrome/Brave: menú → **Instalar aplicación** (o el botón *Instalar* del portal).

> El PC con Laragon y go2rtc debe estar encendido.

---

## 🛠️ Diagnóstico

- `http://localhost:1984` → panel de go2rtc con el estado de cada flujo. Si una cámara no carga, el error aparece ahí.
- Si una cámara HD se ve en negro, probablemente transmite en **H.265**: usa el subflujo también para `_hd` o configura el DVR en H.264.
- Errores de grabaciones: `cache/errores.log` (o la carpeta definida en `CACHE`).
- Después de editar `go2rtc.yaml`: `detener.bat` y luego `iniciar.bat`.
- `go2rtc.example.yaml` trae formatos RTSP alternativos para DVR **Dahua** y **Hikvision**.

---

## 🔒 Seguridad

- **Nunca subas `config.php` ni `go2rtc/go2rtc.yaml`**: tienen la clave del DVR. Ya están en `.gitignore`;
  en el repositorio solo van las versiones `.example`.
- La vista **en vivo no pide contraseña**; las grabaciones sí. Úsalo solo en tu red de casa o por Tailscale,
  **no abras puertos del router a Internet**.
- La API de go2rtc (puerto 1984) muestra las URL RTSP (con la clave) a quien llegue a ese puerto.
  Mantén el firewall de Windows en “redes privadas”.
- `login.php` compara con usuario y clave del DVR y espera 1 s tras cada intento fallido.
- El protocolo del DVR es delicado: un paquete mal formado puede reiniciarlo. Por eso todo acceso pasa por un candado (`dvr_lock`).

---

## 🙏 Créditos

- [go2rtc](https://github.com/AlexxIT/go2rtc) de AlexxIT — servidor de video y reproductor `video-rtc.js` (licencia MIT).
- [OpenXM-DVR](https://github.com/crazy3tr/OpenXM-DVR) — documentación del protocolo binario del DVR.
- [JMuxer](https://github.com/samirkumardas/jmuxer) — reproducción inmediata del H.264 de las grabaciones.
- [TensorFlow.js](https://www.tensorflow.org/js) + [COCO-SSD](https://github.com/tensorflow/tfjs-models/tree/master/coco-ssd) — detector de personas.
- [ffmpeg](https://ffmpeg.org/) — conversión a MP4.
- Tipografías Barlow y Barlow Condensed (Google Fonts).

## 📄 Licencia

[MIT](LICENSE) © 2026 Mario
