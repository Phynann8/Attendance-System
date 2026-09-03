@echo off
setlocal
cd /d "%~dp0"
REM ============================================================
REM  Attendance System - share your LOCAL site on the internet
REM  Uses a free Cloudflare quick tunnel (no signup, no cost).
REM
REM  - Keep this window OPEN while people are using the link.
REM  - The URL is random and changes every time you run this.
REM  - Your PC must stay on and connected to the internet.
REM  - Optional: pass a custom port, e.g.  share.bat 8080
REM ============================================================

set "PORT=%~1"
if "%PORT%"=="" set "PORT=8080"

set "CF=%~dp0cloudflared.exe"
if not exist "%CF%" set "CF=cloudflared.exe"
if not exist "%CF%" (
    echo [!] cloudflared.exe was not found next to this script or on PATH.
    echo     Download it with:
    echo     powershell -Command "Invoke-WebRequest 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile 'cloudflared.exe'"
    pause
    exit /b 1
)

echo [1/2] Making sure the Docker stack is running on port %PORT% ...
docker compose up -d --build
if errorlevel 1 (
    echo [!] Docker failed to start the stack. Is Docker Desktop running?
    pause
    exit /b 1
)

echo [2/2] Opening the FREE public tunnel...
echo.
echo  ==============================================================
echo   YOUR PUBLIC LINK appears a few lines below, it looks like:
echo       https://SOMETHING-HERE.trycloudflare.com
echo   Share that link. Keep this window open. Ctrl+C to stop.
echo  ==============================================================
echo.
"%CF%" tunnel --url http://localhost:%PORT%
