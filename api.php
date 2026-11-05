<?php
// Grabaciones del DVR: calendario, línea de tiempo, vista inmediata, clips y descargas
require __DIR__ . '/config.php';
require __DIR__ . '/dvr.php';

function fail(string $msg, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE));
}
function ok($data): never {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    exit(json_encode($data, JSON_UNESCAPED_UNICODE));
}

require __DIR__ . '/auth.php';
if (!$sesion) fail('Sesión expirada, vuelva a entrar', 401);

$a = $_GET['a'] ?? '';
$abiertos = canales_abiertos();

function canal(string $v): int {
    global $abiertos;
    if (!ctype_digit($v) || !isset($abiertos[(int)$v])) fail('Cámara no habilitada', 403);
    return (int)$v;
}
function fecha(string $v): string {
    if (!preg_match('/^\d{4}-\d\d-\d\d$/', $v) || !checkdate((int)substr($v, 5, 2), (int)substr($v, 8, 2), (int)substr($v, 0, 4))) fail('Fecha inválida');
    return $v;
}

// Conexiones abiertas en esta petición, una por usuario del DVR
$conns = [];
function conn(string $u): array {
    global $conns;
    return $conns[$u] ??= dvr_open($u, DVR_PASS);
}
function cerrar(string $u): void {
    global $conns;
    if (isset($conns[$u])) { @fclose($conns[$u]['s']); unset($conns[$u]); }
}

// Lista de archivos de un día; los días pasados casi no cambian y se guardan una hora
function dia(int $ch, string $date): array {
    global $conns;
    $f = CACHE . "/idx/{$ch}_{$date}.json";
    $ttl = $date < date('Y-m-d') ? 3600 : 60;
    if (is_file($f) && filemtime($f) > time() - $ttl) return json_decode(file_get_contents($f), true);
    if (!isset($conns[DVR_BUSCA])) { dvr_lock('busca'); $conns[DVR_BUSCA] = dvr_open(DVR_BUSCA, DVR_PASS); }
    $files = dvr_search($conns[DVR_BUSCA], $ch, $date);
    @mkdir(CACHE . '/idx', 0777, true);
    file_put_contents($f, json_encode(array_map(fn($x) => ['raw' => base64_encode($x['raw'])] + $x, $files)));
    return dia($ch, $date);
}

function sec(string $t): int { [$h, $m, $s] = hms($t); return $h * 3600 + $m * 60 + $s; }
function hora(int $s): string { return sprintf('%02d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60); }

$CLIPS = CACHE . '/clips';
function clip_key(string $k): string {
    if (!preg_match('/^\d_\d{4}-\d\d-\d\d_\d{6}_\d{1,2}$/', $k)) fail('Clip inválido');
    canal(explode('_', $k)[0]);
    return $k;
}

try {
    // ---------- Calendario: días del mes con grabación ----------
    if ($a === 'days') {
        if (!preg_match('/^(\d{4})-(\d\d)$/', $_GET['month'] ?? '', $m)) fail('Mes inválido');
        $chs = array_map('canal', array_filter(explode(',', $_GET['ch'] ?? ''), 'strlen'));
        $out = [];
        $hoy = date('Y-m-d');
        for ($d = 1; $d <= cal_days_in_month(CAL_GREGORIAN, (int)$m[2], (int)$m[1]); $d++) {
            $date = sprintf('%s-%s-%02d', $m[1], $m[2], $d);
            if ($date > $hoy) break;
            foreach ($chs as $ch) if (dia($ch, $date)) { $out[$date] = true; break; }
        }
        ok(['days' => $out]);
    }

    // ---------- Línea de tiempo: tramos grabados de un día ----------
    if ($a === 'files') {
        $date = fecha($_GET['date'] ?? '');
        $out = [];
        foreach (array_filter(explode(',', $_GET['ch'] ?? ''), 'strlen') as $v) {
            $ch = canal($v);
            $out[$ch] = array_map(fn($x) => [$x['from'], $x['to']], dia($ch, $date));
        }
        ok(['files' => $out]);
    }

    // ---------- Tramo exacto desde el DVR ----------
    // 'stream': entrega cada trozo apenas llega (el navegador lo reproduce al tiro) y de paso lo deja en caché.
    // 'clip':   lo baja completo y responde cuando el MP4 está listo (para descargar).
    // No se usa un ffmpeg de larga duración: en Windows heredaría las conexiones abiertas al DVR
    // y el DVR seguiría viendo al usuario conectado (rechaza un segundo acceso con el mismo usuario).
    if ($a === 'clip' || $a === 'stream') {
        $live = $a === 'stream';
        $ch = canal($_GET['ch'] ?? '');
        $date = fecha($_GET['date'] ?? '');
        if (!preg_match('/^\d\d:\d\d:\d\d$/', $_GET['from'] ?? '')) fail('Hora inválida');
        $min = max(1, min(60, (int)($_GET['min'] ?? 5)));
        $from = sec($_GET['from']);
        $to = min($from + $min * 60, 86399);
        $key = sprintf('%d_%s_%s_%d', $ch, $date, str_replace(':', '', hora($from)), $min);
        $mp4 = "$CLIPS/$key.mp4";
        if (is_file($mp4)) ok(['key' => $key]);   // ya está completo: el navegador lo abre como MP4

        // Turno: cada clic en la línea de tiempo trae uno nuevo y la entrega anterior se detiene sola
        $tok = $_GET['tok'] ?? '';
        if ($live) {
            if (!preg_match('/^\w{1,40}$/', $tok)) fail('Turno inválido');
            @mkdir(CACHE, 0777, true);
            // Las 2 pantallas traen el mismo turno y llegan juntas: se escribe solo si cambió
            if (@file_get_contents(CACHE . '/turno') !== $tok) file_put_contents(CACHE . '/turno', $tok, LOCK_EX);
        }
        // Vacío = se está reescribiendo en ese instante: no cuenta como turno ajeno
        $turno = fn() => !$live || in_array(@file_get_contents(CACHE . '/turno'), [$tok, '', false], true);

        ignore_user_abort(true);   // 'clip' termina aunque se cierre la página; 'stream' corta él mismo y limpia
        set_time_limit(0);
        @mkdir($CLIPS, 0777, true);
        foreach (glob("$CLIPS/*") as $old) if (filemtime($old) < time() - 86400) @unlink($old);   // limpieza: clips de más de un día

        $pieces = [];
        foreach (dia($ch, $date) as $f) {
            $a0 = max($from, sec($f['from']));
            $b0 = min($to, sec($f['to']));
            if ($a0 >= $b0) continue;
            $pieces[] = [$f, hora($a0), hora($b0)];
        }
        if (isset($conns[DVR_BUSCA])) { cerrar(DVR_BUSCA); @unlink(CACHE . '/busca.lock'); }   // libera las búsquedas
        if (!$pieces) fail('No hay grabación de esa cámara en ese horario');

        // El DVR entrega una grabación a la vez por usuario (pero sí varias con usuarios distintos, y cualquiera ve
        // todas las cámaras): si la 2.ª pantalla tiene el mismo usuario que la 1.ª, se pide con el otro usuario.
        $u = $abiertos[$ch];
        if (isset($_GET['with']) && $abiertos[canal($_GET['with'])] === $u) $u = array_values(array_diff(array_keys($USUARIOS), [$u]))[0] ?? $u;
        dvr_lock("dvr_$u");
        if (!$turno()) exit;   // mientras esperaba su turno se pidió otro punto
        if (is_file($mp4)) ok(['key' => $key]);   // otra petición lo armó mientras esperábamos
        if ($live) {
            header('Content-Type: application/octet-stream');
            header('Cache-Control: no-store');
            while (ob_get_level()) ob_end_flush();
        }

        $raw = "$CLIPS/$key.h264";
        $out = fopen($raw, 'wb');
        $fps = 0;
        $c = conn($u);
        try {
            foreach ($pieces as [$f, $pa, $pb]) {
                $f['raw'] = base64_decode($f['raw']);
                $fps = dvr_fetch($c, $f, $pa, $pb, $out, function ($chunk) use ($live, $turno) {
                    if (!$live) return;
                    echo $chunk;
                    flush();
                    // Se eligió otro punto de la línea de tiempo: se corta (el DVR queda libre al instante)
                    if (connection_aborted() || !$turno()) throw new RuntimeException('Cancelado');
                }) ?: $fps;
            }
        } catch (RuntimeException $e) {
            fclose($out);
            @unlink($raw);
            throw $e;
        }
        fclose($out);
        foreach (array_keys($conns) as $u) cerrar($u);
        // El DVR graba a cuadros por segundo fijos; sin ese dato el video quedaría acelerado
        exec(escapeshellarg(FFMPEG) . ' -hide_banner -loglevel error -y -fflags +genpts -r ' . ($fps ?: 16)
            . ' -f h264 -i ' . escapeshellarg($raw) . ' -c copy -aspect 16:9 -movflags +faststart ' . escapeshellarg($mp4) . ' 2>&1', $log, $rc);   // 16:9 también para las cámaras de 944×1080
        @unlink($raw);
        if ($live) exit;   // el navegador ya recibió el video; esto solo deja el MP4 listo para descargar
        if ($rc !== 0 || !is_file($mp4)) fail('No se pudo convertir la grabación: ' . implode(' ', array_slice($log, -2)), 500);
        ok(['key' => $key]);
    }

    // ---------- Video de un clip, con saltos (Range) para la barra de reproducción ----------
    if ($a === 'video' || $a === 'download') {
        $keys = array_map('clip_key', array_slice(explode(',', $_GET['key'] ?? ''), 0, 2));
        foreach ($keys as $k) if (!is_file("$CLIPS/$k.mp4")) fail('El clip ya no está disponible; vuelve a cargarlo', 404);
        $file = "$CLIPS/{$keys[0]}.mp4";
        if (count($keys) === 2) {
            // 2 pantallas: ambas cámaras lado a lado en un solo video
            $file = "$CLIPS/{$keys[0]}__{$keys[1]}.mp4";
            if (!is_file($file)) {
                set_time_limit(0);
                exec(escapeshellarg(FFMPEG) . ' -hide_banner -loglevel error -y -i ' . escapeshellarg("$CLIPS/{$keys[0]}.mp4") . ' -i ' . escapeshellarg("$CLIPS/{$keys[1]}.mp4")
                    . ' -filter_complex "[0:v]scale=1280:720,setsar=1[a];[1:v]scale=1280:720,setsar=1[b];[a][b]hstack=shortest=1"'
                    . ' -c:v libx264 -preset veryfast -crf 24 -movflags +faststart ' . escapeshellarg($file) . ' 2>&1', $log, $rc);
                if ($rc !== 0) { @unlink($file); fail('No se pudo unir las 2 pantallas: ' . implode(' ', array_slice($log, -2)), 500); }
            }
        }
        $size = filesize($file);
        $start = 0;
        $end = $size - 1;
        header('Content-Type: video/mp4');
        header('Accept-Ranges: bytes');
        if ($a === 'download') {
            [$ch, $date, $t] = explode('_', $keys[0]);
            $names = implode('+', array_map(fn($k) => preg_replace('/[^A-Za-z0-9]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT', nombre_canal((int)$k[0]))), $keys));
            header('Content-Disposition: attachment; filename="grabacion_' . $names . "_{$date}_{$t}.mp4\"");
        } elseif (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'] ?? '', $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') $start = max(0, $size - (int)$m[2]);
            else { $start = (int)$m[1]; if ($m[2] !== '') $end = min((int)$m[2], $size - 1); }
            if ($start > $end) { header("Content-Range: bytes */$size"); http_response_code(416); exit; }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Length: ' . ($end - $start + 1));
        $fp = fopen($file, 'rb');
        fseek($fp, $start);
        for ($left = $end - $start + 1; $left > 0 && !feof($fp) && !connection_aborted(); $left -= 65536) echo fread($fp, min(65536, $left));
        exit;
    }
} catch (RuntimeException $e) {
    foreach (array_keys($conns) as $u) cerrar($u);   // antes de responder: el DVR debe ver el cierre ya
    if ($e->getMessage() !== 'Cancelado') @file_put_contents(CACHE . '/errores.log', date('Y-m-d H:i:s') . " $a " . http_build_query($_GET) . ': ' . $e->getMessage() . "\n", FILE_APPEND);
    fail($e->getMessage(), 502);
}
fail('Acción desconocida');
