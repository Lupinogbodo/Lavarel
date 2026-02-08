# Complete Setup Guide - Learning Platform

## Prerequisites Installation

### 1. Install Required Software

#### PHP 8.1+ (Windows)
```powershell
# Download PHP from: https://windows.php.net/download/
# Or use Chocolatey:
choco install php --version=8.1

# Verify installation
php -v
```

#### Composer
```powershell
# Download from: https://getcomposer.org/download/
# Or use Chocolatey:
choco install composer

# Verify installation
composer --version
```

#### MySQL 8.0+ (or PostgreSQL)
```powershell
# Option 1: MySQL with Chocolatey
choco install mysql

# Option 2: Download MySQL Installer
# https://dev.mysql.com/downloads/installer/

# Option 3: Use XAMPP (includes MySQL + Apache)
choco install xampp-81

# Verify MySQL is running
mysql --version
```

#### Redis 6.0+
```powershell
# For Windows, download from:
# https://github.com/microsoftarchive/redis/releases

# Or use Memurai (Redis-compatible for Windows):
choco install memurai-developer

# Or use Docker:
docker run -d -p 6379:6379 redis:6-alpine

# Verify Redis is running
redis-cli ping
# Should return: PONG
```

#### Node.js 16+ (for frontend assets if needed)
```powershell
choco install nodejs-lts

# Verify
node --version
npm --version
```

---

## Step-by-Step Setup

### Step 1: Configure Environment

```powershell
# Navigate to project directory
cd "c:\Users\VINCENT\3D Objects\Lavarel"

# Copy environment file
copy .env.example .env

# Open .env in editor
notepad .env
```

**Edit `.env` file with your settings:**

```env
APP_NAME=LaravelLearningPlatform
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=learning_platform
DB_USERNAME=root
DB_PASSWORD=your_mysql_password

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Redis Database Separation
REDIS_DB_CACHE=1
REDIS_DB_SESSION=2
REDIS_DB_QUEUE=3

# Mail Configuration (for testing)
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@learningplatform.com
MAIL_FROM_NAME="${APP_NAME}"

# Security
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8000
API_REQUIRE_HTTPS=false
```

### Step 2: Install Dependencies

```powershell
# Install PHP dependencies
composer install

# If you get memory errors:
php -d memory_limit=-1 C:\ProgramData\ComposerSetup\bin\composer.phar install
```

### Step 3: Generate Application Key

```powershell
php artisan key:generate
```

### Step 4: Create Database

#### MySQL Method 1: Command Line
```powershell
# Login to MySQL
mysql -u root -p

# In MySQL prompt, run:
CREATE DATABASE learning_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'laravel_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON learning_platform.* TO 'laravel_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### MySQL Method 2: Using XAMPP/phpMyAdmin
1. Start XAMPP Control Panel
2. Start MySQL
3. Open browser: `http://localhost/phpmyadmin`
4. Click "New" to create database
5. Name: `learning_platform`
6. Collation: `utf8mb4_unicode_ci`
7. Click "Create"

#### PostgreSQL (Alternative)
```powershell
# Install PostgreSQL
choco install postgresql

# Create database
psql -U postgres
CREATE DATABASE learning_platform;
CREATE USER laravel_user WITH PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE learning_platform TO laravel_user;
\q

# Update .env:
DB_CONNECTION=pgsql
DB_PORT=5432
```

### Step 5: Run Migrations

```powershell
# Run all migrations to create tables
php artisan migrate

# Expected output:
# Migration table created successfully.
# Migrating: 2024_01_01_000001_create_students_table
# Migrated:  2024_01_01_000001_create_students_table (XX.XXms)
# Migrating: 2024_01_01_000002_create_courses_table
# Migrated:  2024_01_01_000002_create_courses_table (XX.XXms)
# ... (7 migrations total)
```

**If you get errors:**
```powershell
# Fresh start (drops all tables and re-runs migrations)
php artisan migrate:fresh

# Check migration status
php artisan migrate:status
```

### Step 6: Seed Sample Data (Optional)

```powershell
# Create a database seeder first
php artisan make:seeder DemoDataSeeder

# Or manually insert test data via SQL
mysql -u root -p learning_platform < database/seeds/sample_data.sql
```

**Quick test data SQL:**
```sql
-- Insert sample course
INSERT INTO courses (title, code, description, price, available_slots, max_students, status, created_at, updated_at)
VALUES (
    'Laravel Mastery',
    'LAR-2024-001',
    'Complete Laravel development course',
    299.99,
    10,
    50,
    'published',
    NOW(),
    NOW()
);

-- Verify
SELECT * FROM courses;
```

### Step 7: Start Redis Server

```powershell
# If using Redis on Windows:
redis-server

# If using Memurai:
# Already running as Windows service

# If using Docker:
docker start redis
# Or new container:
docker run -d --name redis -p 6379:6379 redis:6-alpine

# Verify Redis is working
redis-cli ping
# Should return: PONG

# Test Redis with Laravel
php artisan tinker
# In tinker:
Cache::put('test', 'Hello Redis', 60);
Cache::get('test');
# Should return: "Hello Redis"
exit
```

### Step 8: Start Queue Worker

```powershell
# Open NEW PowerShell terminal (keep this running)
cd "c:\Users\VINCENT\3D Objects\Lavarel"

# Start queue worker
php artisan queue:work redis --tries=3 --timeout=120

# Expected output:
# Processing: App\Jobs\SendWelcomeEmail
# Processed:  App\Jobs\SendWelcomeEmail

# Leave this terminal running!
```

**Production Queue Setup (Optional):**
```powershell
# Install Supervisor for Windows (queue manager)
# Or use Windows Task Scheduler to keep queue running

# For development, just keep the terminal open
```

### Step 9: Start Laravel Development Server

```powershell
# Open ANOTHER PowerShell terminal
cd "c:\Users\VINCENT\3D Objects\Lavarel"

# Start Laravel server
php artisan serve

# Expected output:
# Starting Laravel development server: http://127.0.0.1:8000
# [Thu Feb  6 10:00:00 2026] PHP 8.1.0 Development Server (http://127.0.0.1:8000) started

# Server is now running at: http://localhost:8000
```

**Custom port (if 8000 is busy):**
```powershell
php artisan serve --port=8080
```

---

## Testing the Application

### Test 1: Check Server is Running

```powershell
# Open browser or use curl:
curl http://localhost:8000

# Or visit in browser:
# http://localhost:8000
```

### Test 2: Access Search Frontend

```
URL: http://localhost:8000/index.html

Expected:
- Search box appears
- Placeholder text: "Search courses..."
- Professional styling
```

**Test Search Functionality:**
1. Type "Laravel" in search box
2. Wait 300ms (debounce delay)
3. Should see API request in Network tab
4. Results appear with highlighting
5. Try arrow keys to navigate
6. Press Enter to select
7. Press Escape to clear

### Test 3: Test API Endpoints

#### A. Course Search API (Public)

```powershell
# Search for courses
curl http://localhost:8000/api/v1/search/courses?query=Laravel

# Expected response:
# {
#   "success": true,
#   "data": [...],
#   "meta": {...}
# }
```

#### B. User Registration

```powershell
# Register new user
curl -X POST http://localhost:8000/api/v1/auth/register `
  -H "Content-Type: application/json" `
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "SecurePass123!@#",
    "password_confirmation": "SecurePass123!@#",
    "phone": "+1234567890",
    "date_of_birth": "1990-01-01"
  }'

# Expected response:
# {
#   "success": true,
#   "message": "Registration successful...",
#   "data": {
#     "student_id": 1,
#     "email": "john@example.com"
#   }
# }
```

#### C. User Login

```powershell
# Login to get token
curl -X POST http://localhost:8000/api/v1/auth/login `
  -H "Content-Type: application/json" `
  -d '{
    "email": "john@example.com",
    "password": "SecurePass123!@#"
  }'

# Expected response:
# {
#   "success": true,
#   "data": {
#     "token": "1|abcdef123456...",
#     "token_type": "Bearer",
#     "student": {...}
#   }
# }

# SAVE THE TOKEN - you'll need it for next requests!
```

#### D. Create Enrollment (Protected)

```powershell
# Save your token from login response
$token = "1|your_actual_token_here"

# Create enrollment (use example payload)
curl -X POST http://localhost:8000/api/v1/enrollments `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer $token" `
  -d @docs/example-enrollment.json

# Or inline JSON:
curl -X POST http://localhost:8000/api/v1/enrollments `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer $token" `
  -d '{
    "student": {
      "first_name": "Jane",
      "last_name": "Smith",
      "email": "jane@example.com",
      "phone": "+1234567890",
      "date_of_birth": "1995-05-15",
      "address": {
        "street": "123 Main St",
        "city": "New York",
        "state": "NY",
        "zip_code": "10001",
        "country": "USA"
      }
    },
    "enrollment": {
      "course_id": 1
    },
    "payment": {
      "amount": 299.99,
      "method": "credit_card"
    }
  }'

# Check queue worker terminal - should see job processing!
```

### Test 4: Verify Queue Jobs

```powershell
# Check queue worker terminal
# You should see:
# [2026-02-06 10:15:30][job_id] Processing: App\Jobs\SendWelcomeEmail
# [2026-02-06 10:15:31][job_id] Processed:  App\Jobs\SendWelcomeEmail
# [2026-02-06 10:15:32][job_id] Processing: App\Jobs\ProcessCourseAccess
# [2026-02-06 10:15:35][job_id] Processed:  App\Jobs\ProcessCourseAccess

# Check email log (since MAIL_MAILER=log)
cat storage/logs/laravel.log | Select-String "Welcome"
```

### Test 5: Verify Cache is Working

```powershell
# Test cache with artisan tinker
php artisan tinker

# In tinker, run:
Cache::put('test_key', 'test_value', 60);
Cache::get('test_key');
# Should return: "test_value"

# Check Redis directly
redis-cli
# In Redis CLI:
KEYS *
GET laravel_database_test_key
exit
```

### Test 6: Run Automated Tests

```powershell
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Feature

# Run debugging tests
php artisan test tests/Debugging/ReplicateBugTest.php
php artisan test tests/Feature/BugFixVerificationTest.php

# Expected output:
#   PASS  Tests\Debugging\ReplicateBugTest
#   ✓ cache invalidated after enrollment created
#   ✓ browser cache headers
#
#   Tests:  X passed
#   Time:   XXs
```

### Test 7: Test Security Features

#### A. Rate Limiting
```powershell
# Try login 10 times quickly
for ($i=1; $i -le 10; $i++) {
    Write-Host "Attempt $i"
    curl -X POST http://localhost:8000/api/v1/auth/login `
      -H "Content-Type: application/json" `
      -d '{"email":"test@test.com","password":"wrong"}'
}

# After 5 attempts, should get:
# {
#   "error": {
#     "code": "TOO_MANY_ATTEMPTS",
#     "message": "Too many login attempts..."
#   }
# }
```

#### B. Security Headers
```powershell
# Check security headers
curl -I http://localhost:8000/api/v1/search/courses

# Should see:
# Content-Security-Policy: ...
# X-Content-Type-Options: nosniff
# X-Frame-Options: DENY
# X-XSS-Protection: 1; mode=block
# Cache-Control: no-store, no-cache...
```

---

## Troubleshooting

### Issue: "Could not find driver"

```powershell
# Enable PHP extensions in php.ini
notepad C:\php\php.ini

# Uncomment these lines:
extension=pdo_mysql
extension=mysqli
extension=openssl
extension=mbstring
extension=redis  # or extension=php_redis.dll

# Restart terminal
```

### Issue: "Connection refused" (MySQL)

```powershell
# Check MySQL is running
net start MySQL80

# Or via XAMPP:
# Start XAMPP Control Panel -> Start MySQL

# Test connection
mysql -u root -p
```

### Issue: "Connection refused" (Redis)

```powershell
# Start Redis server
redis-server

# Or if using Memurai:
net start Memurai

# Or Docker:
docker start redis

# Test connection
redis-cli ping
```

### Issue: Migrations fail

```powershell
# Drop all tables and start fresh
php artisan migrate:fresh

# If still fails, check database exists:
mysql -u root -p
SHOW DATABASES;
USE learning_platform;
SHOW TABLES;
```

### Issue: Queue jobs not processing

```powershell
# Restart queue worker
# Press Ctrl+C in queue worker terminal
php artisan queue:work redis --tries=3 --timeout=120

# Clear failed jobs
php artisan queue:flush

# Check for failed jobs
php artisan queue:failed
```

### Issue: Cache not working

```powershell
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart Redis
redis-cli FLUSHALL
```

### Issue: "419 Page Expired" (CSRF)

```
This is normal for web routes. For API routes:
- Always use Authorization: Bearer {token}
- APIs are stateless, no CSRF needed
```

---

## Quick Reference Commands

### Daily Development Workflow

```powershell
# Terminal 1: Redis (if not running as service)
redis-server

# Terminal 2: Queue Worker
cd "c:\Users\VINCENT\3D Objects\Lavarel"
php artisan queue:work redis --tries=3

# Terminal 3: Laravel Server
cd "c:\Users\VINCENT\3D Objects\Lavarel"
php artisan serve

# Now visit: http://localhost:8000/index.html
```

### Useful Artisan Commands

```powershell
# Check routes
php artisan route:list

# Check configuration
php artisan config:show database
php artisan config:show cache

# Interactive console
php artisan tinker

# Clear everything
php artisan optimize:clear

# View logs
cat storage/logs/laravel.log
cat storage/logs/security.log
cat storage/logs/api.log
```

### Database Commands

```powershell
# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh start (WARNING: Drops all tables!)
php artisan migrate:fresh

# Check migration status
php artisan migrate:status

# Create new migration
php artisan make:migration create_something_table
```

---

## Performance Tips

### Enable OpCache (Production)

```ini
; In php.ini:
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

### Optimize Laravel

```powershell
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Clear all caches (when making changes)
php artisan optimize:clear
```

---

## Production Deployment

When ready for production:

1. **Update .env:**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   API_REQUIRE_HTTPS=true
   ```

2. **Optimize:**
   ```powershell
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Use Supervisor for queues** (Linux) or **Task Scheduler** (Windows)

4. **Use proper web server:** Apache/Nginx instead of `php artisan serve`

---

## Next Steps

1. ✅ Complete setup following steps above
2. ✅ Test all endpoints with provided curl commands
3. ✅ Run automated test suite
4. ✅ Review documentation in `docs/` folder
5. ✅ Prepare for interview using `docs/INTERVIEW_GUIDE.md`

**If you encounter any issues, check the troubleshooting section above!** 🚀
