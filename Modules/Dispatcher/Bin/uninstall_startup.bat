@echo off
setlocal
echo ========================================================
echo  Antigravity Dispatcher - Windows Baslangictan Kaldir
echo ========================================================

set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "SHORTCUT_PATH=%STARTUP_FOLDER%\AntigravityDispatcher.lnk"

if exist "%SHORTCUT_PATH%" (
    del "%SHORTCUT_PATH%"
    echo [OK] Baslangic kisayolu silindi.
) else (
    echo [INFO] Baslangicta zaten kayit bulunamadi.
)

pause
