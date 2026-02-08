@echo off
REM Quick Setup Script for Learning Platform
REM Run this to quickly check if everything is configured correctly

echo ========================================
echo Learning Platform - Quick Setup Test
echo ========================================
echo.

echo [1/8] Checking PHP installation...
php -v
if %errorlevel% neq 0 (
    echo ERROR: PHP not found! Please install PHP 8.1+
    pause
    exit /b 1
)
echo ✓ PHP installed
echo.

echo [2/8] Checking Composer installation...
composer --version
if %errorlevel% neq 0 (
    echo ERROR: Composer not found! Please install Composer
    pause
    exit /b 1
)
echo ✓ Composer installed
echo.

echo [3/8] Checking MySQL connection...
mysql --version
if %errorlevel% neq 0 (
    echo WARNING: MySQL client not found in PATH
    echo Please verify MySQL is installed and running
) else (
    echo ✓ MySQL client installed
)
echo.

echo [4/8] Checking Redis connection...
redis-cli ping
if %errorlevel% neq 0 (
    echo WARNING: Redis not responding
    echo Please start Redis server
) else (
    echo ✓ Redis is running
)
echo.

echo [5/8] Checking .env file...
if exist .env (
    echo ✓ .env file exists
) else (
    echo Creating .env from .env.example...
    copy .env.example .env
    echo ✓ .env file created - Please edit it with your settings!
)
echo.

echo [6/8] Installing Composer dependencies...
composer install --no-interaction
if %errorlevel% neq 0 (
    echo ERROR: Composer install failed!
    pause
    exit /b 1
)
echo ✓ Dependencies installed
echo.

echo [7/8] Generating application key...
php artisan key:generate
echo ✓ Application key generated
echo.

echo [8/8] Testing Laravel artisan...
php artisan --version
if %errorlevel% neq 0 (
    echo ERROR: Laravel artisan not working!
    pause
    exit /b 1
)
echo ✓ Laravel artisan working
echo.

echo ========================================
echo Setup Check Complete!
echo ========================================
echo.
echo Next Steps:
echo 1. Edit .env file with your database credentials
echo 2. Create database: learning_platform
echo 3. Run: php artisan migrate
echo 4. Run: php artisan serve
echo.
echo For detailed instructions, see SETUP_GUIDE.md
echo.
pause
