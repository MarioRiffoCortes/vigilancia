<?php
// Acceso a grabaciones: sesión iniciada con el usuario ramz del DVR (ver login.php)
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$sesion = !empty($_SESSION['ramz']);
session_write_close();   // streams y descargas son largos: no retener el bloqueo de la sesión
