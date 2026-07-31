@echo off
title PL Sync - Laravel Scheduler
color 0B
echo ========================================================
echo   Starting PL Sync Data Warehouse Scheduler...
echo ========================================================
echo.
echo Membaca konfigurasi jadwal (cron) dari database...
echo.
echo Biarkan window terminal ini tetap terbuka (minimize saja).
echo Jika ditutup, maka fitur Auto Schedule tidak akan berjalan.
echo.
echo Menjalankan command: php artisan schedule:work
echo.
php artisan schedule:work
pause
