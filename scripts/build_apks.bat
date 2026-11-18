@echo off
setlocal enabledelayedexpansion

echo ======================================================================
echo           KAAMLY MOBILE - AUTOMATED APK BUILD SCRIPT
echo ======================================================================
echo.

:: 1. Check for Flutter
where flutter >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Flutter SDK is not detected in your Windows PATH.
    echo.
    echo To build APKs on this machine:
    echo 1. Download Flutter SDK: https://storage.googleapis.com/flutter_infra_release/releases/stable/windows/flutter_windows_3.24.5-stable.zip
    echo 2. Extract to C:\flutter
    echo 3. Add C:\flutter\bin to your System Environment PATH.
    echo.
    echo Alternatively, push this repository to GitHub - the automated
    echo GitHub Actions workflow will build both APKs in ~3 minutes!
    echo ======================================================================
    pause
    exit /b 1
)

echo [1/3] Building Shared Package...
cd /d "%~dp0..\mobile\shared"
call flutter pub get

echo.
echo [2/3] Building Customer App APK...
cd /d "%~dp0..\mobile\customer-app"
call flutter pub get
call flutter build apk --release

echo.
echo [3/3] Building Provider App APK...
cd /d "%~dp0..\mobile\provider-app"
call flutter pub get
call flutter build apk --release

echo.
echo ======================================================================
echo SUCCESS: BOTH APKS BUILT SUCCESSFULLY!
echo ======================================================================
echo Customer APK: %~dp0..\mobile\customer-app\build\app\outputs\flutter-apk\app-release.apk
echo Provider APK: %~dp0..\mobile\provider-app\build\app\outputs\flutter-apk\app-release.apk
echo ======================================================================
pause
