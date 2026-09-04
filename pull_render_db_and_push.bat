@echo off
chcp 65001 >nul
title Sync Database from Render and Push to GitHub
cd /d "%~dp0"

echo =========================================================
echo    🔄 ดึง Database ล่าสุดจาก Render และ Push ขึ้น GitHub
echo =========================================================
echo.

set CONFIG_FILE=.render_url
if exist "%CONFIG_FILE%" (
    set /p RENDER_URL=<"%CONFIG_FILE%"
)

if "%RENDER_URL%"=="" (
    echo กรุณาใส่ URL เว็บ Render ของคุณ (ใส่แค่ครั้งแรก):
    echo ตัวอย่าง: https://lemon-solve.onrender.com
    set /p RENDER_URL="URL: "
    echo %RENDER_URL%>"%CONFIG_FILE%"
    echo บันทึก URL เรียบร้อยแล้ว
    echo.
)

echo [1/3] กำลังดาวน์โหลด Database ล่าสุดจาก %RENDER_URL% ...
powershell -Command "try { $u = '%RENDER_URL%'.TrimEnd('/') + '/api.php?action=download_database&token=admin1234'; Invoke-WebRequest -Uri $u -OutFile 'data/highspec.db' -TimeoutSec 30; Write-Host ' [OK] ดาวน์โหลดไฟล์ highspec.db สำเร็จ!' -ForegroundColor Green } catch { Write-Host ' [!] ไม่สามารถดาวน์โหลดได้ กรุณาตรวจสอบว่าเซิร์ฟเวอร์เปิดอยู่หรือไม่: ' $_ -ForegroundColor Red; exit 1 }"

if %errorlevel% neq 0 (
    echo.
    echo [!] การดาวน์โหลดล้มเหลว กรุณาตรวจสอบลิงก์หรือรหัสผ่าน
    pause
    exit /b 1
)

echo.
echo [2/3] กำลัง Commit ฐานข้อมูลล่าสุดเข้า Git...
"C:\Program Files\Git\bin\git.exe" add data/highspec.db
"C:\Program Files\Git\bin\git.exe" add .
"C:\Program Files\Git\bin\git.exe" commit -m "Auto-sync latest database from Render [%date% %time%]"

echo.
echo [3/3] กำลัง Push ขึ้น GitHub...
"C:\Program Files\Git\bin\git.exe" push origin main

if %errorlevel% equ 0 (
    echo.
    echo =========================================================
    echo    ✅ สำเร็จ! ซิงค์ฐานข้อมูลและส่งขึ้น GitHub เรียบร้อยแล้ว!
    echo =========================================================
) else (
    echo.
    echo [!] ไม่สามารถ Push ขึ้น GitHub ได้ กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต
)

echo.
pause
