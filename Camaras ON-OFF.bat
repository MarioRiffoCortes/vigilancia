@echo off
title Camaras - Encender / Apagar
set "APACHE=C:\laragon\bin\apache\httpd-2.4.62-240904-win64-VS17\bin"
set "URL=http://localhost/vigilancia/"
set "BRAVE=C:\Program Files\BraveSoftware\Brave-Browser\Application\brave.exe"
set "APPS=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Brave Apps"
cd /d "%~dp0go2rtc"

tasklist /fi "imagename eq go2rtc.exe" | find /i "go2rtc.exe" >nul
if not errorlevel 1 goto apagar

:encender
echo.
echo   Encendiendo camaras...
tasklist /fi "imagename eq httpd.exe" | find /i "httpd.exe" >nul
if errorlevel 1 (
  if exist "%APACHE%\httpd.exe" (
    echo   Iniciando servidor web Apache...
    powershell -NoProfile -Command "Start-Process -WindowStyle Hidden -WorkingDirectory '%APACHE%' '%APACHE%\httpd.exe'"
  ) else (
    echo   No encontre Apache. Abre Laragon y pulsa "Iniciar todo".
  )
)
rem En segundo plano: sin ventana en la barra de tareas
  powershell -NoProfile -Command "Start-Process -WindowStyle Hidden .\go2rtc.exe -ArgumentList '-config go2rtc.yaml'"
timeout /t 3 /nobreak >nul
call :abrir
echo   [ENCENDIDO] Las camaras estan activas.
timeout /t 3 >nul
exit /b

:apagar
echo.
echo   Apagando camaras...
taskkill /im go2rtc.exe /f >nul 2>&1
echo   [APAGADO] Las camaras ya no se transmiten.
echo   (Apache/Laragon sigue encendido para tus otros proyectos.)
timeout /t 3 >nul
exit /b

:abrir
rem 1) La aplicacion instalada (acceso directo que crea Brave al instalarla)
for %%F in ("%APPS%\Vigilancia*.lnk") do (
  start "" "%%~fF"
  exit /b
)
rem 2) Sin instalar: ventana de aplicacion de Brave, sin barra de direcciones
if exist "%BRAVE%" (
  start "" "%BRAVE%" --app=%URL%
  exit /b
)
rem 3) Ultimo recurso: el navegador predeterminado
start "" %URL%
exit /b
