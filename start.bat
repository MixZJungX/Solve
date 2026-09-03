@echo off
title HIGHSPEC.GG Auto Job Runner Web
cd /d "%~dp0"

echo =======================================================
echo         HIGHSPEC.GG - DIRECT API AUTO RUNNER
echo =======================================================
echo.

:: Detect PHP
set PHP_BIN=php
where php >nul 2>&1
if %errorlevel% neq 0 (
    set "PHP_BIN=C:\Users\ninek\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
)

echo Starting PHP Server on http://localhost:8000 ...
echo Press Ctrl+C in this window to stop the server.
echo.

:: Open browser automatically after 1 second
start "" http://localhost:8000

:: Run PHP Server with custom php.ini and router.php
"%PHP_BIN%" -c php.ini -S 127.0.0.1:8000 router.php

pause
