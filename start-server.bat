@echo off
title RADMA Website Local Server
cd /d "%~dp0"

rem ===== Check PHP is installed =====
where php >nul 2>nul
if errorlevel 1 (
    echo [X] PHP not found. Install PHP or add it to PATH, then try again.
    pause
    exit /b 1
)

set PORT=8090

rem ===== Stop any previous server owning this port (that process only) =====
powershell -NoProfile -Command "Get-NetTCPConnection -LocalPort %PORT% -State Listen -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique | ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }"

echo ==========================================================
echo   RADMA website local server is running
echo.
echo   On this device:   http://localhost:%PORT%/
echo.
echo   From a phone on the same network:
powershell -NoProfile -Command "Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } | ForEach-Object { '      http://' + $_.IPAddress + ':%PORT%/' }"
echo.
echo   To stop: close this window or press Ctrl+C
echo ==========================================================
echo.

rem ===== Open the browser after a short delay, once the server has had time to start =====
start "" powershell -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Seconds 3; Start-Process 'http://localhost:%PORT%/'"

php -S 0.0.0.0:%PORT% -t "%~dp0." router.php
pause
