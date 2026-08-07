# deploy.ps1
# Script ini untuk mempermudah Anda melakukan push ke GitHub lalu deploy otomatis ke Hostinger.

Write-Host "1. Menambahkan perubahan ke Git..." -ForegroundColor Cyan
git add -A

$commitMsg = Read-Host "Masukkan pesan commit (kosongkan untuk default 'update')"
if ([string]::IsNullOrWhiteSpace($commitMsg)) {
    $commitMsg = "update"
}

Write-Host "2. Melakukan Commit..." -ForegroundColor Cyan
git commit -m $commitMsg

Write-Host "3. Push ke GitHub (master)..." -ForegroundColor Cyan
git push origin master

Write-Host "4. Menyuruh Hostinger melakukan Pull dari GitHub..." -ForegroundColor Cyan
# Asumsi path folder Hostinger: public_html 
ssh -p 65002 u664715641@46.202.186.86 "cd public_html ; git fetch origin ; git reset --hard origin/master"

Write-Host "Selesai! Berhasil di-deploy ke Hostinger." -ForegroundColor Green
