<?php
// Cliente del DVR: protocolo binario antiguo (NetSDK) en el puerto 34567.
// Este DVR rechaza el login JSON de XMeye; usa estructuras binarias fijas.
// Referencia: github.com/crazy3tr/OpenXM-DVR (docs/WIRE_PROTOCOL.md), ajustado a lo que este equipo acepta.
// Ojo: un paquete mal formado puede reiniciar el DVR, y tiene un único canal de reproducción.
// Todo acceso pasa por dvr_lock().

const DVR_ERR = [
    203 => 'Contraseña incorrecta',
    205 => 'El DVR rechazó el formato de acceso',
    208 => 'Ese usuario ya está conectado al DVR. Espera un minuto y reintenta',
    107 => 'El usuario no tiene permiso para esto',
];

function dvr_pkt(int $sid, int $seq, int $type, string $body, int $ver = 0): string {
    return pack('CCxxVVCCvV', 0xff, $ver, $sid, $seq, 0, 0, $type, strlen($body)) . $body;
}

function dvr_read($s, int $n): string {
    $b = '';
    while (strlen($b) < $n) {
        $c = fread($s, $n - strlen($b));
        if ($c === '' || $c === false) break;
        $b .= $c;
    }
    return $b;
}

function dvr_recv($s): ?array {
    $h = dvr_read($s, 20);
    if (strlen($h) < 20) return null;
    $u = unpack('Chead/Cver/x2/Vsid/Vseq/Ctot/Ccur/vtype/Vlen', $h);
    $u['body'] = $u['len'] ? dvr_read($s, $u['len']) : '';
    return $u;
}

// Un solo acceso al DVR a la vez: tiene un único canal de reproducción
// y no deja entrar dos veces con el mismo usuario.
// flock() no bloquea entre hilos de Apache en Windows; crear el archivo en exclusiva ('x') sí.
// El dueño lo renueva mientras descarga (dvr_beat); si deja de hacerlo 30 s, se da por muerto.
// 'dvr_<usuario>' = reproducción (el DVR entrega una grabación a la vez por usuario); 'busca' = búsquedas.
function dvr_lock(string $name = 'dvr'): void {
    @mkdir(CACHE, 0777, true);
    $f = CACHE . "/$name.lock";
    while (!($h = @fopen($f, 'x'))) {
        clearstatcache(true, $f);
        if (@filemtime($f) < time() - 30) @unlink($f);
        else usleep(200000);
    }
    fclose($h);
    register_shutdown_function(fn() => @unlink($f));
    $GLOBALS['dvr_lock_file'] = $f;
}
function dvr_beat(): void { if (isset($GLOBALS['dvr_lock_file'])) @touch($GLOBALS['dvr_lock_file']); }

function dvr_connect() {
    $s = @stream_socket_client('tcp://' . DVR_HOST . ':' . DVR_PORT, $en, $es, 5);
    if (!$s) throw new RuntimeException('El DVR no responde');
    stream_set_timeout($s, 15);
    return $s;
}

function dvr_open(string $user, string $pass, int $retry = 12): array {
    $s = dvr_connect();
    // Login antiguo de 76 bytes: usuario[32] + clave en texto plano[32] + 12 ceros
    $body = str_pad(substr($user, 0, 31), 32, "\0") . str_pad(substr($pass, 0, 31), 32, "\0") . str_repeat("\0", 12);
    fwrite($s, dvr_pkt(0, 0, 1000, $body, 1));
    $r = dvr_recv($s);
    $ret = $r && strlen($r['body']) >= 20 ? unpack('V', substr($r['body'], 8, 4))[1] : 0;
    if ($ret !== 100) {
        fclose($s);
        // Tras desconectarse, el DVR tarda un instante en liberar al usuario
        if ($ret === 208 && $retry > 0) { sleep(1); return dvr_open($user, $pass, $retry - 1); }
        throw new RuntimeException(DVR_ERR[$ret] ?? "El DVR rechazó el acceso (código $ret)");
    }
    return ['s' => $s, 'sid' => unpack('V', substr($r['body'], 16, 4))[1], 'seq' => 1];
}

function dvr_close(array $c): void { fclose($c['s']); }

function dvr_req(array &$c, int $type, string $body): array {
    fwrite($c['s'], dvr_pkt($c['sid'], $c['seq']++, $type, $body));
    $r = dvr_recv($c['s']);
    if (!$r) throw new RuntimeException('El DVR no respondió');
    return $r;
}

function hms(string $t): array { return array_map('intval', explode(':', $t)) + [0, 0, 0]; }

// Archivos grabados de un canal en un día: [['name','kb','from','to','raw'], ...]
function dvr_search(array &$c, int $ch, string $date): array {
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    $files = [];
    $start = [0, 0, 0];
    while (true) {
        $p = str_repeat("\0", 140);
        $p = substr_replace($p, pack('V', $c['sid']), 0, 4);
        $p = substr_replace($p, pack('V', $ch), 12, 4);
        $p = substr_replace($p, 'h264', 16, 4);
        $p = substr_replace($p, pack('V', 0x2A), 40, 4);        // '*' = todo tipo de grabación
        $p = substr_replace($p, pack('V', 0xFFFF), 72, 4);
        $p = substr_replace($p, pack('V8', $y, $m, $d, 0, $start[0], $start[1], $start[2], 0), 76, 32);
        $p = substr_replace($p, pack('V8', $y, $m, $d, 0, 23, 59, 59, 0), 108, 32);
        $b = dvr_req($c, 1440, $p)['body'];
        $ret = strlen($b) >= 8 ? unpack('V', substr($b, 4, 4))[1] : 0;
        if ($ret === 119) break;                                  // sin archivos
        if ($ret !== 100) throw new RuntimeException(DVR_ERR[$ret] ?? "Búsqueda rechazada (código $ret)");
        $n = unpack('V', substr($b, 16, 4))[1];
        $before = count($files);
        for ($i = 0; $i < $n; $i++) {
            $rec = substr($b, 24 + $i * 184, 184);
            if (strlen($rec) < 180) break;
            $name = strstr(substr($rec, 8, 108) . "\0", "\0", true);
            if (!preg_match('#/(\d\d)\.(\d\d)\.(\d\d)-(\d\d)\.(\d\d)\.(\d\d)\[#', $name, $t)) continue;
            $from = "$t[1]:$t[2]:$t[3]";
            $to = "$t[4]:$t[5]:$t[6]";
            if ($to <= $from) $to = '23:59:59';                    // termina pasada la medianoche
            $files[$name] = ['name' => $name, 'kb' => unpack('V', substr($rec, 4, 4))[1], 'from' => $from, 'to' => $to, 'raw' => substr($rec, 116, 64)];
        }
        // El DVR entrega máximo 64 por respuesta: se sigue desde el final del último
        if ($n < 64 || count($files) === $before) break;
        $last = end($files)['to'];
        if ($last >= '23:59:59') break;
        $start = hms($last);
    }
    usort($files, fn($a, $b) => strcmp($a['from'], $b['from']));
    return array_values($files);
}

// Descarga un tramo exacto [from, to] de un archivo y lo agrega a $out (H.264 con cabeceras del DVR).
// Devuelve los cuadros por segundo que informa el DVR.
function dvr_fetch(array &$c, array $file, string $from, string $to, $out, callable $progress): int {
    $t = array_values(unpack('V16', $file['raw']));
    [$t[4], $t[5], $t[6]] = hms($from);
    [$t[12], $t[13], $t[14]] = hms($to);
    $body = fn(int $act) => str_pad(pack('VVViV', $c['sid'], 0, 0, $act, 0) . str_pad($file['name'], 108, "\0") . pack('V16', ...$t), 200, "\0");

    // Los datos llegan por una segunda conexión que se "reclama" para esta sesión
    $d = dvr_connect();
    fwrite($d, dvr_pkt($c['sid'], 0, 1424, $body(7)));
    $r = dvr_recv($d);
    $ret = $r && strlen($r['body']) >= 8 ? unpack('V', substr($r['body'], 4, 4))[1] : 0;
    if ($ret !== 100) { fclose($d); throw new RuntimeException("El DVR no entregó la grabación (código $ret)"); }
    $r = dvr_req($c, 1420, $body(8));
    $ret = strlen($r['body']) >= 8 ? unpack('V', substr($r['body'], 4, 4))[1] : 0;
    if ($ret !== 100) { fclose($d); throw new RuntimeException("El DVR no inició la descarga (código $ret)"); }

    $fps = 0;
    $head = '';            // se descarta el GOP incompleto inicial: el video debe partir en un cuadro completo
    $last = time();
    try {
    while ($h = dvr_recv($d)) {
        if ($h['type'] !== 1426 || $h['len'] === 0) break;    // el DVR termina con un paquete vacío al llegar a "to"
        $chunk = $h['body'];
        if ($head !== null) {
            $head .= $chunk;
            $i = strpos($head, "\x00\x00\x01\xFD");
            if ($i === false) { $head = substr($head, -3); $chunk = ''; }
            else { $chunk = substr($head, $i); $fps = ord($head[$i + 5] ?? "\0") & 0x3F; $head = null; }
        }
        if ($chunk !== '') { fwrite($out, $chunk); $progress($chunk); }
        // Sin señales en la conexión de control, el DVR corta la entrega
        if (time() - $last >= 2) { dvr_req($c, 1006, pack('V4', $c['sid'], 0, 0, 0)); dvr_beat(); $last = time(); }
    }
    } finally { fclose($d); }   // al cortar, el DVR libera la entrega apenas se cierra esta conexión
    return $fps;
}
