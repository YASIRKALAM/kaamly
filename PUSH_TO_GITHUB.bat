@echo off
title Push Kaamly to GitHub
color 0B
echo ======================================================================
echo              PUSHING KAAMLY TO GITHUB REPOSITORY
echo ======================================================================
echo.
echo Repository: https://github.com/YASIRKALAM/kaamly.git
echo Branch:     main
echo.
echo [NOTE] If prompted:
echo - Username: YASIRKALAM
echo - Password: Enter your GitHub Personal Access Token (not your account password)
echo ======================================================================
echo.

"d:\Study\tools\mingit\cmd\git.exe" -C "%~dp0." push -u origin main

echo.
echo ======================================================================
pause
