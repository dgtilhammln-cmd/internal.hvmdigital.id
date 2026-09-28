@echo off
chcp 65001 >nul
echo.
echo  ========================================
echo     HVM Digital ^| Deploy Script
echo  ========================================
echo.

echo [1/4] Menambahkan semua perubahan ke Git...
git add -A

set /p "commitMsg=Masukkan pesan commit (kosongkan = update): "
if "%commitMsg%"=="" set "commitMsg=update"

echo.
echo [2/4] Commit: %commitMsg%
git commit -m "%commitMsg%"

echo.
echo [3/4] Push ke GitHub (master)...
git push origin master
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Push ke GitHub gagal. Cek SSH key / koneksi.
    pause
    exit /b 1
)

echo.
echo [4/4] Pull ke server Hostinger via SSH...
ssh -p 65002 u664715641@46.202.186.86 "cd domains/internal.hvmdigital.id/public_html && git fetch origin && git reset --hard origin/master"
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Deploy ke Hostinger gagal. Cek SSH key / koneksi.
    pause
    exit /b 1
)

echo.
echo  SELESAI - Berhasil di-deploy ke internal.hvmdigital.id
echo.
pause
