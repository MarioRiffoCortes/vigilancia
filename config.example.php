<?php
// Configuración compartida del portal
// Copia este archivo como config.php y completa tus datos (config.php NO se sube a GitHub).
date_default_timezone_set('America/Santiago');

// Nombres que se muestran en pantalla (clave = nombre del flujo en go2rtc.yaml)
$NOMBRES = [
    'camara_1' => 'Camara 1',
    'camara_2' => 'Camara 2',
    'camara_3' => 'Camara 3',
    'camara_4' => 'Camara 4',
];

// Conexión al DVR en 3 etapas: DVR_BUSCA = vivo (va en go2rtc/go2rtc.yaml);
// las grabaciones de cada canal (0 = cámara 1) se piden con su propio usuario.
$USUARIOS = [
    'usuario_rec1' => [0, 1],
    'usuario_rec2' => [2, 3],
];
const DVR_PASS  = 'CAMBIAR_CLAVE';   // misma clave para los tres usuarios
const DVR_BUSCA = 'usuario_vivo';    // calendario, línea de tiempo y login del portal (el DVR admite una sesión por usuario)

const DVR_HOST = '192.168.1.100';    // IP del DVR (la misma de la app XVR Pro)
const DVR_PORT = 34567;
const FFMPEG   = __DIR__ . '/ffmpeg/ffmpeg.exe';
const CACHE    = __DIR__ . '/cache'; // o una ruta en otro disco, ej. 'E:/cache'

// Canal del DVR => usuario que entrega sus grabaciones
function canales_abiertos(): array {
    global $USUARIOS;
    $out = [];
    foreach ($USUARIOS as $u => $chs) foreach ($chs as $ch) $out[$ch] = $u;
    ksort($out);
    return $out;
}

// Nombre de una cámara a partir de su canal del DVR (0 = camara_1)
function nombre_canal(int $ch): string {
    global $NOMBRES;
    return $NOMBRES['camara_' . ($ch + 1)] ?? 'Cámara ' . ($ch + 1);
}
