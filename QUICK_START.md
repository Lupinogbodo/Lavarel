# Quick Start Commands Reference

## One-Time Setup

```powershell
# 1. Run quick setup script
.\setup.bat

# 2. Edit .env with your credentials
notepad .env

# 3. Create database
mysql -u root -p
CREATE DATABASE learning_platform;
EXIT;

# 4. Run migrations
php artisan migrate

# 5. Load sample data (optional)
mysql -u root -p learning_platform < database/seeders/sample_data.sql
```

## Daily Development

```powershell
# Start all services (automated)
.\start-dev.bat

# Or manually in separate terminals:
redis-server                              # Terminal 1
php artisan queue:work redis --tries=3    # Terminal 2
php artisan serve                         # Terminal 3
```

## Endpoints to Test

### Frontend
- Search UI: http://localhost:8000/index.html

### API Endpoints

#### Public (No Auth)
```powershell
# Search courses
curl http://localhost:8000/api/v1/search/courses?query=Laravel

# Register
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"first_name":"John","last_name":"Doe","email":"john@test.com","password":"SecurePass123!@#","password_confirmation":"SecurePass123!@#","phone":"+1234567890","date_of_birth":"1990-01-01"}'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@test.com","password":"SecurePass123!@#"}'
```

#### Protected (Needs Token)
```powershell
# Save token from login
$token = "YOUR_TOKEN_HERE"

# Get user info
curl http://localhost:8000/api/v1/auth/user \
  -H "Authorization: Bearer $token"

# Create enrollment
curl -X POST http://localhost:8000/api/v1/enrollments \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $token" \
  -d '{"student":{"first_name":"Jane","last_name":"Smith","email":"jane@test.com","phone":"+1234567890","date_of_birth":"1995-05-15","address":{"street":"123 Main St","city":"New York","state":"NY","zip_code":"10001","country":"USA"}},"enrollment":{"course_id":1},"payment":{"amount":299.99,"method":"credit_card"}}'

# Logout
curl -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Authorization: Bearer $token"
```

## Useful Commands

```powershell
# Clear caches
php artisan optimize:clear

# Check routes
php artisan route:list

# Run tests
php artisan test

# View logs
cat storage/logs/laravel.log

# Check queue status
php artisan queue:failed
php artisan queue:flush
```

## Troubleshooting

```powershell
# Reset database
php artisan migrate:fresh

# Restart queue worker
# Press Ctrl+C in queue terminal, then:
php artisan queue:work redis --tries=3

# Check Redis
redis-cli ping

# Test database connection
php artisan tinker
DB::connection()->getPdo();
```

## Testing Checklist

- [ ] Redis is running (`redis-cli ping`)
- [ ] MySQL is running (check XAMPP/services)
- [ ] Database created (`learning_platform`)
- [ ] Migrations ran (`php artisan migrate:status`)
- [ ] .env configured correctly
- [ ] Queue worker is running
- [ ] Laravel server is running (http://localhost:8000)
- [ ] Frontend loads (http://localhost:8000/index.html)
- [ ] Search API works (try searching "Laravel")
- [ ] Can register new user
- [ ] Can login and get token
- [ ] Can create enrollment with token
- [ ] Queue jobs process (check queue worker terminal)

See SETUP_GUIDE.md for detailed troubleshooting!
