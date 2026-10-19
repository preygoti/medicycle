@echo off
title MediCycle Live Server Runner
echo ==============================================
echo       Starting MediCycle Local & Live Server
echo ==============================================

echo [1/3] Starting MySQL Database...
start /b "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone >nul 2>&1

timeout /t 2 /nobreak >nul

echo [2/3] Starting PHP Web Server on Port 8000...
start /b "" "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t "%~dp0." >nul 2>&1

timeout /t 2 /nobreak >nul

echo [3/3] Starting Cloudflare Live Public Tunnel...
echo.
echo ==============================================================
echo Local URL: http://127.0.0.1:8000
echo Your public live HTTPS link will appear below:
echo ==============================================================
echo.
"%~dp0bin\cloudflared.exe" tunnel --url http://127.0.0.1:8000
pause
