@echo off
setlocal
echo ========================================================
echo  Antigravity Dispatcher - Windows Baslangica Ekleme
echo ========================================================

set "CURRENT_DIR=%~dp0"
set "CURRENT_DIR=%CURRENT_DIR:~0,-1%"
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "SHORTCUT_PATH=%STARTUP_FOLDER%\AntigravityDispatcher.lnk"
set "TARGET_SCRIPT=%CURRENT_DIR%\start_silent.vbs"

echo [1/2] Windows Baslangic klasorune kisayol olusturuluyor...
powershell -NoProfile -Command "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut('%SHORTCUT_PATH%'); $s.TargetPath = 'wscript.exe'; $s.Arguments = '\"%TARGET_SCRIPT%\"'; $s.WorkingDirectory = '%CURRENT_DIR%'; $s.Save()"

if exist "%SHORTCUT_PATH%" (
    echo [2/2] BASARILI! Kisayol eklendi:
    echo       "%SHORTCUT_PATH%"
    echo.
    echo Artik bilgisayariniz her acildiginda Dispatcher sessizce
    echo arka planda (pencere acilmadan) 4000 portunda calisacaktir!
) else (
    echo [HATA] Kisayol olusturulamadi!
)

echo.
pause
