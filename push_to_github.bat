@echo off
title Push Lemon Shop to GitHub
cd /d "%~dp0"

echo ===================================================
echo     Pushing Lemon Shop to GitHub (MixZJungX/Solve)
echo ===================================================
echo.

"C:\Program Files\Git\bin\git.exe" add .
"C:\Program Files\Git\bin\git.exe" commit -m "Update Lemon shop Solve Helper"
"C:\Program Files\Git\bin\git.exe" branch -M main
"C:\Program Files\Git\bin\git.exe" remote set-url origin https://github.com/MixZJungX/Solve.git

echo.
echo Connecting to GitHub...
echo (If a browser or login popup appears, please sign in or click Authorize)
echo.

"C:\Program Files\Git\bin\git.exe" push -u origin main

if %errorlevel% equ 0 (
    echo.
    echo ===================================================
    echo     SUCCESS! Uploaded to GitHub successfully!
    echo ===================================================
) else (
    echo.
    echo [!] If failed, please check your internet or GitHub login.
)

echo.
pause
