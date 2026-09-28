# deploy.ps1 — HVM Digital Deploy Script
# Jalankan: .\deploy.ps1

Write-Host ""
Write-Host "  ========================================" -ForegroundColor Cyan
Write-Host "     HVM Digital | Deploy Script"         -ForegroundColor Cyan
Write-Host "  ========================================" -ForegroundColor Cyan
Write-Host ""

# Masuk ke folder yang benar jika script dijalankan dari parent dir
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

Write-Host "[1/4] Menambahkan semua perubahan ke Git..." -ForegroundColor Yellow
git add -A

$commitMsg = Read-Host "Masukkan pesan commit (kosongkan = 'update')"
if ([string]::IsNullOrWhiteSpace($commitMsg)) { $commitMsg = "update" }

Write-Host ""
Write-Host "[2/4] Commit: $commitMsg" -ForegroundColor Yellow
git commit -m $commitMsg

Write-Host ""
Write-Host "[3/4] Push ke GitHub (master)..." -ForegroundColor Yellow
git push origin master
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] Push ke GitHub gagal. Cek SSH key / koneksi." -ForegroundColor Red
    Read-Host "Tekan Enter untuk keluar"
    exit 1
}

Write-Host ""
Write-Host "[4/4] Deploy ke Hostinger via SSH..." -ForegroundColor Yellow
ssh -p 65002 u664715641@46.202.186.86 "cd domains/internal.hvmdigital.id/public_html && git fetch origin && git reset --hard origin/master"
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] Deploy ke Hostinger gagal. Cek SSH key / koneksi." -ForegroundColor Red
    Read-Host "Tekan Enter untuk keluar"
    exit 1
}

Write-Host ""
Write-Host "  SELESAI - Berhasil di-deploy ke internal.hvmdigital.id" -ForegroundColor Green
Write-Host ""
Read-Host "Tekan Enter untuk keluar"
