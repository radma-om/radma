@echo off
title RADMA Website Server Stop
set PORT=8090

powershell -NoProfile -Command "$p = Get-NetTCPConnection -LocalPort %PORT% -State Listen -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique; if ($p) { $p | ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }; Write-Output '[OK] RADMA website local server stopped.' } else { Write-Output '[i] No server is running on port %PORT%.' }"

timeout /t 3 >nul
