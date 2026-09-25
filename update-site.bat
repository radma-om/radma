@echo off
title RADMA - Publish website update
cd /d "%~dp0"

where git >nul 2>nul
if errorlevel 1 (
    echo [X] Git not found. Install Git for Windows, then try again.
    pause
    exit /b 1
)

rem ===== Safety net: the secrets file must NEVER be tracked by Git (the repo is public) =====
git ls-files --error-unmatch private/mazbot-config.php >nul 2>nul
if not errorlevel 1 (
    echo [X] STOP: private/mazbot-config.php is tracked by Git. It contains secrets.
    echo     Run:  git rm --cached private/mazbot-config.php   and ask for help before publishing.
    pause
    exit /b 1
)

echo ==========================================================
echo   Publish website update  ^(GitHub -^> radma.co auto-deploy^)
echo ==========================================================
echo.

set "CHANGES="
for /f "delims=" %%i in ('git status --porcelain') do set "CHANGES=1"
if not defined CHANGES (
    echo Nothing to publish - no changes since the last update.
    echo.
    pause
    exit /b 0
)

echo Changed files:
git status --short
echo.

set "MSG="
set /p "MSG=Short description of this update (Enter = Update website): "
if not defined MSG set "MSG=Update website"
set "MSG=%MSG:"='%"

git add -A
git commit -q -m "%MSG%"
if errorlevel 1 (
    echo [X] Commit failed.
    pause
    exit /b 1
)

git pull --rebase -q origin main
if errorlevel 1 (
    echo [X] Could not merge with the latest version on GitHub. Ask for help before publishing.
    pause
    exit /b 1
)

git push origin main
if errorlevel 1 (
    echo [X] Push failed. Check the internet connection and the GitHub login, then run this file again.
    pause
    exit /b 1
)

echo.
echo [OK] Published. https://radma.co updates automatically within about 20 seconds.
echo.
pause
