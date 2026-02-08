# Deployment Strategy - Learning Platform

## Deployment Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         PRODUCTION                               │
│                                                                  │
│  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐  │
│  │   Region 1   │      │   Region 2   │      │   Region 3   │  │
│  │   (Primary)  │      │   (Backup)   │      │     (DR)     │  │
│  └──────────────┘      └──────────────┘      └──────────────┘  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                          STAGING                                 │
│                    (Pre-production Testing)                      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        DEVELOPMENT                               │
│                   (Feature Development)                          │
└─────────────────────────────────────────────────────────────────┘
```

## Deployment Options

### Option 1: Traditional VPS (DigitalOcean, Linode)

**Pros**:
- Full control
- Cost-effective for small-medium scale
- Simple setup

**Cons**:
- Manual scaling
- More maintenance
- Less automated

### Option 2: Cloud Platform (AWS, GCP, Azure)

**Recommended for Production**

**Architecture**:
```
                    ┌─────────────────┐
                    │   Route 53      │
                    │   (DNS)         │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │  CloudFront     │
                    │  (CDN)          │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │  ALB/ELB        │
                    │  (Load Balancer)│
                    └────────┬────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
┌───────▼───────┐   ┌────────▼────────┐   ┌──────▼──────┐
│   EC2/ECS     │   │    EC2/ECS      │   │  EC2/ECS    │
│   Laravel     │   │    Laravel      │   │  Laravel    │
│   App Server  │   │    App Server   │   │  App Server │
└───────┬───────┘   └────────┬────────┘   └──────┬──────┘
        │                    │                    │
        └────────────────────┼────────────────────┘
                             │
        ┌────────────────────┼───────────────────────────┐
        │                    │                           │
┌───────▼─────────┐  ┌───────▼──────────┐  ┌────────▼──────────┐
│   RDS MySQL     │  │  ElastiCache     │  │   SQS/Redis       │
│   (Primary)     │  │  (Redis Cache)   │  │   (Queue)         │
│                 │  │                  │  │                   │
│   Read Replica  │  │  Replica Nodes   │  │   Workers (ECS)   │
└─────────────────┘  └──────────────────┘  └───────────────────┘
```

### Option 3: Container Orchestration (Kubernetes)

**For Large Scale**

```yaml
# deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      containers:
      - name: laravel
        image: learning-platform:latest
        ports:
        - containerPort: 8000
        env:
        - name: APP_ENV
          value: "production"
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
```

## Step-by-Step Deployment Guide

### 1. Prepare Application

```bash
# Clone repository
git clone https://github.com/your-org/learning-platform.git
cd learning-platform

# Install dependencies
composer install --optimize-autoloader --no-dev

# Set up environment
cp .env.example .env
php artisan key:generate

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. Database Setup

```bash
# Run migrations
php artisan migrate --force

# Seed initial data (if needed)
php artisan db:seed --class=ProductionSeeder
```

### 3. Configure Web Server

#### Nginx Configuration

```nginx
# /etc/nginx/sites-available/learning-platform

server {
    listen 80;
    server_name learningplatform.com www.learningplatform.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name learningplatform.com www.learningplatform.com;
    
    root /var/www/learning-platform/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/learningplatform.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/learningplatform.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
    
    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Increase timeout for long requests
        fastcgi_read_timeout 300;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
    
    # Static file caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

### 4. Queue Workers Setup

#### Supervisor Configuration

```ini
# /etc/supervisor/conf.d/learning-platform-worker.conf

[program:learning-platform-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/learning-platform/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/learning-platform/storage/logs/worker.log
stopwaitsecs=3600

[program:learning-platform-email-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/learning-platform/artisan queue:work redis --queue=emails --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/learning-platform/storage/logs/email-worker.log
```

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 5. Scheduled Tasks (Cron)

```bash
# Edit crontab
crontab -e

# Add Laravel scheduler
* * * * * cd /var/www/learning-platform && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Zero-Downtime Deployment

#### A. Using Deployer

Install Deployer:
```bash
composer require deployer/deployer --dev
```

Create deploy.php:
```php
<?php
namespace Deployer;

require 'recipe/laravel.php';

set('application', 'Learning Platform');
set('repository', 'git@github.com:your-org/learning-platform.git');
set('keep_releases', 5);

host('production')
    ->set('hostname', 'learningplatform.com')
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '/var/www/learning-platform');

// Tasks
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:migrate',
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'artisan:queue:restart',
    'deploy:publish',
]);

after('deploy:failed', 'deploy:unlock');
```

Deploy:
```bash
vendor/bin/dep deploy production
```

#### B. Using Laravel Envoy

```php
@servers(['web' => 'deploy@learningplatform.com'])

@task('deploy', ['on' => 'web'])
    cd /var/www/learning-platform
    
    # Enter maintenance mode
    php artisan down
    
    # Pull latest changes
    git pull origin main
    
    # Install dependencies
    composer install --optimize-autoloader --no-dev
    
    # Run migrations
    php artisan migrate --force
    
    # Clear and cache
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    # Restart queue workers
    php artisan queue:restart
    
    # Exit maintenance mode
    php artisan up
@endtask
```

Run:
```bash
vendor/bin/envoy run deploy
```

### 7. Docker Deployment

#### Dockerfile

```dockerfile
FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Copy configurations
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisor.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port
EXPOSE 80

# Start services
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

#### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "80:80"
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    volumes:
      - ./storage:/var/www/storage
    depends_on:
      - mysql
      - redis
    networks:
      - learning-platform

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: learning_platform
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - learning-platform

  redis:
    image: redis:6-alpine
    command: redis-server --appendonly yes
    volumes:
      - redis-data:/data
    networks:
      - learning-platform

  queue-worker:
    build:
      context: .
      dockerfile: Dockerfile
    command: php artisan queue:work --sleep=3 --tries=3
    depends_on:
      - mysql
      - redis
    networks:
      - learning-platform

volumes:
  mysql-data:
  redis-data:

networks:
  learning-platform:
    driver: bridge
```

Deploy:
```bash
docker-compose up -d --build
```

## CI/CD Pipeline

### GitHub Actions

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          
      - name: Install Dependencies
        run: composer install
        
      - name: Run Tests
        run: php artisan test
        
  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Deploy to Server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.HOST }}
          username: ${{ secrets.USERNAME }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd /var/www/learning-platform
            git pull origin main
            composer install --optimize-autoloader --no-dev
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart
            sudo supervisorctl restart all
```

## Monitoring & Alerting

### Application Monitoring

```php
// config/services.php
'sentry' => [
    'dsn' => env('SENTRY_LARAVEL_DSN'),
    'environment' => env('APP_ENV'),
],
```

### Server Monitoring

- **New Relic**: Application performance monitoring
- **Datadog**: Infrastructure monitoring
- **Pingdom**: Uptime monitoring

### Log Management

```bash
# Install and configure Filebeat for ELK Stack
sudo apt-get install filebeat
sudo filebeat modules enable nginx mysql

# Send logs to Elasticsearch
sudo filebeat setup
sudo service filebeat start
```

## Backup Strategy

### Database Backups

```bash
# /usr/local/bin/backup-db.sh

#!/bin/bash
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="/backups/database"
DB_NAME="learning_platform"

# Create backup
mysqldump -u root -p${DB_PASSWORD} ${DB_NAME} | gzip > ${BACKUP_DIR}/backup_${TIMESTAMP}.sql.gz

# Upload to S3
aws s3 cp ${BACKUP_DIR}/backup_${TIMESTAMP}.sql.gz s3://backups/database/

# Cleanup old backups (keep 30 days)
find ${BACKUP_DIR} -name "backup_*.sql.gz" -mtime +30 -delete
```

Schedule:
```bash
# Daily at 2 AM
0 2 * * * /usr/local/bin/backup-db.sh
```

## Rollback Strategy

```bash
# Quick rollback script
#!/bin/bash

# Get previous release
cd /var/www/learning-platform
CURRENT=$(readlink current)
PREVIOUS=$(ls -t releases | sed -n '2p')

# Swap symlink
ln -nfs releases/$PREVIOUS current

# Clear cache
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart services
php artisan queue:restart
sudo supervisorctl restart all

echo "Rolled back to $PREVIOUS"
```

## Performance Optimization

### OPcache Configuration

```ini
# /etc/php/8.1/fpm/conf.d/opcache.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.validate_timestamps=0  # Production only
```

### PHP-FPM Tuning

```ini
# /etc/php/8.1/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

## Scaling Strategy

### Vertical Scaling
1. Increase server resources (CPU, RAM)
2. Optimize database queries
3. Tune PHP-FPM settings

### Horizontal Scaling
1. Add more application servers
2. Use load balancer
3. Implement session sharing (Redis)
4. Use read replicas for database

## Health Checks

```php
// routes/api.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => DB::connection()->getDatabaseName(),
        'cache' => Cache::get('health_check') ? 'ok' : 'error',
        'queue' => Queue::size() < 1000 ? 'ok' : 'warning',
    ]);
});
```

## Post-Deployment Checklist

- [ ] All migrations ran successfully
- [ ] Queue workers are running
- [ ] Cron jobs are scheduled
- [ ] SSL certificate is valid
- [ ] Caches are cleared and warmed
- [ ] Logs are being written
- [ ] Monitoring is active
- [ ] Backups are configured
- [ ] Health checks passing
- [ ] API endpoints responding
- [ ] Frontend assets loaded
- [ ] Email sending works
- [ ] Payment gateway connected
- [ ] Database connections stable
- [ ] Redis cache working
