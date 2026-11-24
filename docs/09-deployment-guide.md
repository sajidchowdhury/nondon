# Deployment Guide
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Status:** Design document — pending approval

---

## 1. Deployment Overview

### 1.1 Target environment

- **Single server** (initial deployment).
- **OS:** Ubuntu 22.04 LTS or 24.04 LTS.
- **Web server:** Nginx.
- **Application server:** PHP-FPM 8.3+.
- **Database:** PostgreSQL 15+.
- **SSL:** Let's Encrypt (free, auto-renewing).

### 1.2 Production architecture

```
Internet
    │
    ▼
┌─────────────────────────────┐
│  Nginx (reverse proxy)      │
│  - SSL/TLS termination      │
│  - Static file serving      │
│  - Reverse proxy to PHP-FPM │
│  Port: 80 (redirect) / 443  │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  PHP-FPM 8.3+               │
│  - Laravel application      │
│  - Multiple workers         │
│  - Socket connection        │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  PostgreSQL 15+             │
│  - Database: nondon         │
│  - User: nondon_app         │
│  - Socket connection        │
└─────────────────────────────┘
```

---

## 2. Server Requirements

### 2.1 Minimum specifications (3-5 branches)

| Resource | Minimum | Recommended |
|---|---|---|
| CPU | 2 cores | 4 cores |
| RAM | 4 GB | 8 GB |
| Storage | 50 GB SSD | 100 GB SSD |
| Bandwidth | 1 TB/month | Unlimited |
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |

### 2.2 Software requirements

| Software | Version |
|---|---|
| PHP | 8.3+ |
| Extensions | pdo, pdo_pgsql, pgsql, mbstring, xml, bcmath, ctype, json, tokenizer, openssl, fileinfo, curl, gd, zip |
| Composer | 2.x |
| PostgreSQL | 15+ |
| Nginx | 1.20+ |
| Node.js | 20+ (for asset building) |
| Supervisor | (for queue workers) |

---

## 3. Initial Server Setup

### 3.1 Update system

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget gnupg lsb-release ca-certificates software-properties-common
```

### 3.2 Install PHP 8.3

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mbstring php8.3-xml php8.3-zip php8.3-curl php8.3-gd \
    php8.3-pgsql php8.3-bcmath php8.3-intl php8.3-readline
```

### 3.3 Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3.4 Install PostgreSQL 15

```bash
sudo sh -c 'echo "deb https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg
sudo apt update
sudo apt install -y postgresql-15 postgresql-contrib
```

### 3.5 Install Nginx

```bash
sudo apt install -y nginx
```

### 3.6 Install Supervisor (for queue workers)

```bash
sudo apt install -y supervisor
```

---

## 4. Database Setup

### 4.1 Create database and user

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE nondon WITH ENCODING 'UTF8';
CREATE USER nondon_app WITH ENCRYPTED PASSWORD 'strong_password_here';
GRANT ALL PRIVILEGES ON DATABASE nondon TO nondon_app;
ALTER DATABASE nondon OWNER TO nondon_app;
\q
```

### 4.2 Configure PostgreSQL

Edit `/etc/postgresql/15/main/postgresql.conf`:
```ini
listen_addresses = 'localhost'
max_connections = 100
shared_buffers = 256MB
effective_cache_size = 1GB
work_mem = 4MB
maintenance_work_mem = 64MB
log_min_duration_statement = 500  # Log slow queries (>500ms)
```

Edit `/etc/postgresql/15/main/pg_hba.conf`:
```ini
# Allow local connections from the app
local   nondon      nondon_app                          md5
host    nondon      nondon_app      127.0.0.1/32         md5
host    nondon      nondon_app      ::1/128              md5
```

Restart PostgreSQL:
```bash
sudo systemctl restart postgresql
sudo systemctl enable postgresql
```

---

## 5. Application Deployment

### 5.1 Clone repository

```bash
sudo mkdir -p /var/www/nondon
sudo chown $USER:$USER /var/www/nondon
git clone https://github.com/sajidchowdhury/nondon.git /var/www/nondon
cd /var/www/nondon
```

### 5.2 Install dependencies

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build  # or: npm run prod
```

### 5.3 Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```ini
APP_NAME=Nondon
APP_ENV=production
APP_KEY=base64:generated_key
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nondon
DB_USERNAME=nondon_app
DB_PASSWORD=strong_password_here

CACHE_DRIVER=file  # or redis when available
QUEUE_CONNECTION=database  # or redis when available
SESSION_DRIVER=database
SESSION_LIFETIME=120  # No auto-logout; this is just session cookie lifetime

MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_email_password
MAIL_FROM_ADDRESS=reports@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 5.4 Run migrations and seed

```bash
php artisan migrate --force
php artisan db:seed --force
```

### 5.5 Set permissions

```bash
sudo chown -R www-data:www-data /var/www/nondon
sudo chown -R $USER:www-data /var/www/nondon/storage /var/www/nondon/bootstrap/cache
sudo chmod -R 775 /var/www/nondon/storage /var/www/nondon/bootstrap/cache
```

### 5.6 Optimize for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 6. Nginx Configuration

### 6.1 Site configuration

Create `/etc/nginx/sites-available/nondon`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    root /var/www/nondon/public;
    index index.php index.html;

    # SSL certificates (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';";

    # Upload size (for evidence uploads)
    client_max_body_size 10M;

    # Main Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }
    location ~* \.(env|json|lock|md|yaml|yml)$ {
        deny all;
    }

    # Static asset caching
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Health check
    location = /api/health {
        access_log off;
        try_files $uri /index.php?$query_string;
    }
}
```

### 6.2 Enable site

```bash
sudo ln -s /etc/nginx/sites-available/nondon /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 7. SSL Certificate (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

- Certificates auto-renew via cron.
- Renewal test: `sudo certbot renew --dry-run`.

---

## 8. PHP-FPM Configuration

Edit `/etc/php/8.3/fpm/pool.d/www.conf`:

```ini
[www]
user = www-data
group = www-data
listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500

php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 10M
php_admin_value[max_execution_time] = 60
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl enable php8.3-fpm
```

---

## 9. Queue Worker (Supervisor)

### 9.1 Supervisor configuration

Create `/etc/supervisor/conf.d/nondon-worker.conf`:

```ini
[program:nondon-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nondon/artisan queue:work database --sleep=3 --tries=3 --max-time=60
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/nondon/storage/logs/worker.log
stopwaitsecs=3600
```

### 9.2 Start worker

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start nondon-worker:*
```

---

## 10. Laravel Scheduler

### 10.1 Cron entry

```bash
sudo crontab -u www-data -e
```

Add:
```cron
* * * * * cd /var/www/nondon && php artisan schedule:run >> /dev/null 2>&1
```

---

## 11. Backup Configuration

### 11.1 Database backup script

Create `/var/www/nondon/scripts/backup.sh`:

```bash
#!/bin/bash
set -e

BACKUP_DIR="/var/backups/nondon"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/nondon_db_$DATE.sql.gz"

mkdir -p $BACKUP_DIR

# Database backup
PGPASSWORD="strong_password_here" pg_dump -U nondon_app -h 127.0.0.1 nondon | gzip > $BACKUP_FILE

# Keep only last 4 weeks of backups (weekly backup = 4 files)
find $BACKUP_DIR -name "nondon_db_*.sql.gz" -mtime +28 -delete

# Optional: copy to remote/offsite
# scp $BACKUP_FILE user@remote:/backups/

echo "Backup completed: $BACKUP_FILE"
```

### 11.2 Make executable and schedule

```bash
sudo chmod +x /var/www/nondon/scripts/backup.sh
sudo crontab -u www-data -e
```

Add (weekly backup, Sunday 2 AM):
```cron
0 2 * * 0 /var/www/nondon/scripts/backup.sh >> /var/log/nondon-backup.log 2>&1
```

**Recommendation:** Change to daily backup:
```cron
0 2 * * * /var/www/nondon/scripts/backup.sh >> /var/log/nondon-backup.log 2>&1
```

### 11.3 Backup verification

Monthly test restore:
```bash
gunzip < /var/backups/nondon/nondon_db_YYYYMMDD_HHMMSS.sql.gz | psql -U nondon_app -h 127.0.0.1 nondon_test
```

---

## 12. Firewall Configuration

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## 13. Monitoring

### 13.1 Health check

Laravel health check endpoint at `/api/health`:
```json
{
    "status": "healthy",
    "checks": {
        "database": "ok",
        "storage_writable": "ok",
        "queue": "ok"
    }
}
```

### 13.2 Log monitoring

- Application logs: `/var/www/nondon/storage/logs/laravel.log`
- Nginx logs: `/var/log/nginx/nondon-access.log`, `/var/log/nginx/nondon-error.log`
- PHP-FPM logs: `/var/log/php8.3-fpm.log`
- PostgreSQL logs: `/var/log/postgresql/postgresql-15-main.log`
- Queue worker logs: `/var/www/nondon/storage/logs/worker.log`

### 13.3 Disk space monitoring

```bash
# Add to crontab
0 8 * * * df -h / | awk 'NR==2 && int($5) > 85 {system("mail -s \"Disk Space Alert\" admin@your-domain.com")}'
```

---

## 14. Deployment Updates

### 14.1 Update process

```bash
cd /var/www/nondon

# Maintenance mode
php artisan down

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Run migrations
php artisan migrate --force

# Clear and re-cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers
sudo supervisorctl restart nondon-worker:*

# Bring app back up
php artisan up
```

### 14.2 Zero-downtime deployment (future)

For zero-downtime updates, use a deployment tool like Laravel Envoyer or Deployer that handles:
- Atomic symlink switch.
- Health check before traffic switch.
- Instant rollback.

---

## 15. Branch Device Setup

### 15.1 POS device requirements

- Laptop or tablet with modern browser (Chrome, Edge, Firefox).
- Thermal receipt printer (58mm or 80mm) connected via USB.
- Barcode scanner (USB HID or Bluetooth).
- Barcode label printer (Xprinter XP-420B, G-Printer GP-3120TUC, or Rongta RP400H).
- Internet connection (primary).
- Power backup (UPS recommended).

### 15.2 Browser configuration

- Enable JavaScript.
- Allow pop-ups for printing.
- Set homepage to the POS URL.
- Disable browser autofill for security.
- Enable persistent storage (for IndexedDB offline cache).

### 15.3 Printer setup

- Install printer drivers on the POS device.
- Configure browser print settings:
  - Disable headers and footers.
  - Set correct paper size (58mm, 80mm, or A4).
  - Set margins to 0 for thermal receipts.
- Test print from the POS interface.

---

## 16. Initial Data Setup

### 16.1 Post-deployment seed data

After initial deployment:

1. **Create Main Branch Admin account:**
   ```bash
   php artisan nondon:create-admin
   ```
   Enter username, password, full name.

2. **Create branches:**
   - Main Branch (code: M)
   - B1, B2, B3
   - Online channel (code: ONLINE)

3. **Create current fiscal year:**
   - FY 25_26 (if deploying after July 1, 2025)
   - Or the appropriate current fiscal year

4. **Configure payment methods:**
   - Cash, bKash, Nagad, Bank transfer, Card

5. **Configure return policy:**
   - 7-day return period
   - 7-day defect claim period

6. **Create employee accounts:**
   - One supervisor per branch
   - Salespersons per branch
   - Online team members and supervisor

7. **Create suppliers and initial products** (via admin UI).

8. **Record opening stock** (if migrating from existing system):
   - Create a purchase for opening stock.
   - Receive garments.
   - Print and attach barcode labels.

---

## 17. Rollback Procedure

### 17.1 Application rollback

```bash
cd /var/www/nondon

php artisan down

# Roll back to previous commit
git log --oneline -5  # Find the last stable commit
git checkout <previous_commit_hash>

# Reinstall dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Roll back migrations (if needed)
php artisan migrate:rollback --step=1 --force

# Clear cache
php artisan optimize:clear

# Restart workers
sudo supervisorctl restart nondon-worker:*

php artisan up
```

### 17.2 Database restore from backup

```bash
# Stop the application
php artisan down

# Drop and recreate the database
sudo -u postgres psql
DROP DATABASE nondon;
CREATE DATABASE nondon OWNER nondon_app;
\q

# Restore from backup
gunzip < /var/backups/nondon/nondon_db_YYYYMMDD_HHMMSS.sql.gz | psql -U nondon_app -h 127.0.0.1 nondon

# Bring app back up
php artisan up
```

---

## 18. Security Hardening Checklist

- [ ] SSH key-based authentication only (disable password auth).
- [ ] Firewall configured (UFW).
- [ ] Fail2ban installed for SSH protection.
- [ ] Automatic security updates enabled (`unattended-upgrades`).
- [ ] APP_DEBUG=false in production .env.
- [ ] Database credentials in .env only (not in code).
- [ ] .env file not world-readable (`chmod 600 .env`).
- [ ] SSL certificate installed and auto-renewing.
- [ ] HSTS header enabled.
- [ ] Security headers configured in Nginx.
- [ ] PHP-FPM running as www-data (not root).
- [ ] PostgreSQL not exposed to external network.
- [ ] Backup script not storing password in plaintext (use .pgpass file).
- [ ] Log files rotated (logrotate configured).
- [ ] Unnecessary PHP extensions disabled.
- [ ] Nginx server tokens disabled (`server_tokens off`).

---

## 19. Post-Deployment Verification

### 19.1 Smoke test

After deployment, verify:
- [ ] Application loads at HTTPS URL.
- [ ] Admin can log in.
- [ ] POS interface loads.
- [ ] Barcode scan works.
- [ ] Sale completion works (test sale).
- [ ] Receipt prints correctly (no tax line).
- [ ] Stock availability query works.
- [ ] Transfer request can be created.
- [ ] Cash handover works.
- [ ] Offline mode works (disconnect internet, make sale, reconnect, sync).
- [ ] Scheduled report email sends correctly.
- [ ] Backup script runs successfully.
- [ ] Health check endpoint returns healthy.

---

*End of Deployment Guide*
