<?php
// Login de grabaciones: mismo usuario y clave que ramz en el DVR
require __DIR__ . '/config.php';
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

if (isset($_GET['salir'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (hash_equals(DVR_BUSCA, (string)($_POST['usuario'] ?? '')) && hash_equals(DVR_PASS, (string)($_POST['clave'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['ramz'] = true;
        header('Location: grabaciones.php');
        exit;
    }
    sleep(1);   // frena intentos repetidos
    $error = 'Usuario o contraseña incorrectos';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#101820">
<title>Vigilancia · Acceso</title>
<link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root{--ink:#101820;--deck:#16212C;--rail:#243242;--fog:#D9E1E8;--dim:#7E8E9F;--lamp:#F2A93B;--alarm:#F0565B;--cond:"Barlow Condensed","Arial Narrow",system-ui,sans-serif;--sans:"Barlow",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:grid;place-items:center;padding:16px;background:var(--ink);background-image:radial-gradient(120% 80% at 50% -10%,#116fd4 0%,var(--ink) 60%);color:var(--fog);font:15px/1.4 var(--sans)}
form{width:100%;max-width:340px;display:grid;gap:14px;padding:24px;background:var(--deck);border:1px solid var(--rail);border-radius:12px}
h1{font:700 24px/1 var(--cond)}
label{display:grid;gap:6px;color:var(--dim);font-weight:500}
input{font:inherit;color:var(--fog);background:var(--ink);border:1px solid var(--rail);border-radius:8px;padding:10px 12px}
input:focus-visible,button:focus-visible,a:focus-visible{outline:2px solid var(--lamp);outline-offset:2px}
button{border:0;border-radius:8px;padding:11px;background:var(--lamp);color:#1B1305;font:600 17px/1 var(--cond);letter-spacing:.03em;cursor:pointer}
.err{color:var(--alarm)}
a{color:var(--dim);text-align:center}
</style>
</head>
<body>
<form method="post">
  <h1>Grabaciones</h1>
  <?php if ($error): ?><p class="err" role="alert"><?= $error ?></p><?php endif ?>
  <label>Usuario<input name="usuario" autocomplete="username" autocapitalize="none" required autofocus></label>
  <label>Contraseña<input name="clave" type="password" autocomplete="current-password" required></label>
  <button>Entrar</button>
  <a href="./">Volver a En vivo</a>
</form>
</body>
</html>
