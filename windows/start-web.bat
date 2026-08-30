@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-web.ps1" %*
set exitCode=%errorlevel%
endlocal & exit /b %exitCode%
