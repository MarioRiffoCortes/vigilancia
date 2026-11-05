@echo off
title Camaras - inicio
cd /d "%~dp0go2rtc"
tasklist /fi "imagename eq go2rtc.exe" | find /i "go2rtc.exe" >nul
if errorlevel 1 (
  echo Iniciando servidor de video go2rtc...
  rem En segundo plano: sin ventana en la barra de tareas
  powershell -NoProfile -Command "Start-Process -WindowStyle Hidden .\go2rtc.exe -ArgumentList '-config go2rtc.yaml'"
  timeout /t 3 /nobreak >nul
) else (
  echo go2rtc ya estaba en ejecucion.
)
start "" http://localhost/vigilancia/
