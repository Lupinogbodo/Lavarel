@echo off
REM Start all required services for Learning Platform
REM This script starts Laravel server, Queue worker, and optionally Redis

echo ========================================
echo Starting Learning Platform Services
echo ========================================
echo.

REM Check if Redis is needed
echo Checking Redis...
redis-cli ping >nul 2>&1
if %errorlevel% neq 0 (
    echo Redis not running. Starting Redis...
    start "Redis Server" cmd /k "redis-server"
    timeout /t 3 >nul
) else (
    echo ✓ Redis already running
)

REM Start Queue Worker in new window
echo Starting Queue Worker...
start "Laravel Queue Worker" cmd /k "cd /d %~dp0 && php artisan queue:work redis --tries=3 --timeout=120"
timeout /t 2 >nul

REM Start Laravel Development Server in new window
echo Starting Laravel Server...
start "Laravel Server" cmd /k "cd /d %~dp0 && php artisan serve"
timeout /t 2 >nul

echo.
echo ========================================
echo Services Started!
echo ========================================
echo.
echo ✓ Redis Server (if wasn't running)
echo ✓ Queue Worker (http://localhost:8000 in separate window)
echo ✓ Laravel Server (http://localhost:8000 in separate window)
echo.
echo Frontend URL: http://localhost:8000/index.html
echo API Base URL: http://localhost:8000/api/v1
echo.
echo To stop services: Close the terminal windows
echo.
pause
