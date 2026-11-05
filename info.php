<?php
// Datos técnicos de una cámara para el panel del celular.
// Solo entrega códec, audio y bytes recibidos: nunca la URL del DVR ni sus credenciales.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$src = $_GET['src'] ?? '';
if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $src)) { http_response_code(400); exit('{}'); }

$ctx = stream_context_create(['http' => ['timeout' => 2]]);
$raw = @file_get_contents('http://127.0.0.1:1984/api/streams?src=' . rawurlencode($src), false, $ctx);
$d = $raw ? json_decode($raw, true) : null;
if (!is_array($d)) { http_response_code(503); exit('{}'); }

$out = ['video' => null, 'audio' => null, 'bytes' => 0, 'viewers' => count($d['consumers'] ?? [])];
foreach ($d['producers'] ?? [] as $p) {
    $out['bytes'] += (int)($p['bytes_recv'] ?? 0);
    // Lo que ofrece el DVR, ej. "audio, recvonly, PCMA/8000"
    foreach ($p['medias'] ?? [] as $m) {
        $parts = array_map('trim', explode(',', $m));
        $codec = $parts[2] ?? '';
        if ($parts[0] === 'audio' && !$out['audio']) $out['audio'] = ['codec' => $codec];
        if ($parts[0] === 'video' && !$out['video']) $out['video'] = ['codec' => $codec];
    }
    // Detalle del códec que realmente se está recibiendo
    foreach ($p['receivers'] ?? [] as $r) {
        $c = $r['codec'] ?? [];
        $type = $c['codec_type'] ?? '';
        if ($type === 'video') {
            $out['video'] = [
                'codec'   => strtoupper($c['codec_name'] ?? ($out['video']['codec'] ?? '')),
                'profile' => $c['profile'] ?? null,
                'level'   => isset($c['level']) ? $c['level'] / 10 : null,
            ];
        } elseif ($type === 'audio') {
            $out['audio'] = [
                'codec'    => strtoupper($c['codec_name'] ?? ($out['audio']['codec'] ?? '')),
                'rate'     => $c['sample_rate'] ?? null,
                'channels' => $c['channels'] ?? null,
            ];
        }
    }
}
echo json_encode($out);
