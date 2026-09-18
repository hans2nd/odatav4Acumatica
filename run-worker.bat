@echo off
title PL Sync - Laravel Queue Worker
color 0A
echo ========================================================
echo   Starting PL Sync Queue Worker (Background Jobs)...
echo ========================================================
echo.
echo Biarkan window terminal ini tetap terbuka (minimize saja).
echo Jika ditutup, maka sinkronisasi yang dijadwalkan (maupun 
echo klik 'Sync All') tidak akan dieksekusi di background.
echo.
echo Menjalankan command: php artisan queue:work --timeout=3600
echo.
php artisan queue:work --timeout=3600
pause
