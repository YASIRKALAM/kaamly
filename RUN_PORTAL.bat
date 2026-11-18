@echo off
title Kaamly Web Portal Server
color 0A
echo ======================================================================
echo             STARTING KAAMLY OPERATIONS & FINANCE PORTAL
echo ======================================================================
echo.
echo Portal URL: http://localhost:3000
echo.

if exist "%~dp0..\.venv\Scripts\python.exe" (
    "%~dp0..\.venv\Scripts\python.exe" "%~dp0scripts\run_portal.py"
) else if exist "d:\Study\code\.venv\Scripts\python.exe" (
    "d:\Study\code\.venv\Scripts\python.exe" "%~dp0scripts\run_portal.py"
) else (
    python "%~dp0scripts\run_portal.py"
)

pause
