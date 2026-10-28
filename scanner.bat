@echo off
cd /d "%~dp0"
php scan.php %*
set "SCAN_EXIT=%ERRORLEVEL%"
echo.
echo Rapport : reports\rapport-audit-php.html
pause
exit /b %SCAN_EXIT%
