# Standard Hosting Blueprint: Local Ubuntu Server + Cloudflare Tunnels & DuckDNS

This document serves as the permanent reference blueprint for deploying and self-hosting web applications (Laravel, PHP, Node.js) on your local Ubuntu server environment.

---

## 1. Network & Environment Profile

- **Server Hostname:** `psis-kb-server`
- **Server Local IP:** `192.168.8.19` (secondary `192.168.8.20`)
- **Gateway / Router:** `192.168.8.1` (MikroTik Dual-WAN: Mekong_Net & Online)
- **Local DNS Resolver:** AdGuard Home (`192.168.8.19:8088` / `192.168.8.20:53`)
- **Reverse Proxy Manager:** Nginx Proxy Manager in Docker (`192.168.8.19:81`)
- **Tunnel Service:** Cloudflare Zero Trust (`cloudflared` daemon on systemd)
- **Dynamic DNS:** DuckDNS (`*.duckdns.org` account: `Phynann8@github`)

---

## 2. Server Stack Standards

### A. Multi-PHP Versioning
Always install specific PHP versions via Ondřej Surý PPA (`ppa:ondrej/php`) so new Laravel apps (requiring PHP 8.2 / 8.4) coexist alongside legacy PHP 5.6/7.x apps without breaking them:
```bash
sudo apt-get install -y php8.4-fpm php8.4-cli php8.4-curl php8.4-mbstring \
php8.4-xml php8.4-zip php8.4-sqlite3 php8.4-mysql php8.4-bcmath php8.4-intl composer
```

### B. Node.js LTS
Modern frontend tooling (Vite 8, Tailwind v4) requires Node.js $\ge$ 20.19 / 22:
```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs
```

---

## 3. Project Deployment Procedure

### Step 1: Isolated Directory
Never place new apps directly in `/var/www/html`. Always use dedicated folders:
```bash
sudo mkdir -p /var/www/<project-name>
sudo chown -R $USER:$USER /var/www/<project-name>
git clone <repo-url> /var/www/<project-name>
cd /var/www/<project-name>
```

### Step 2: Build Dependencies
```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

### Step 3: Environment & Database
```bash
cp .env.example .env
php artisan key:generate
# If SQLite:
touch database/database.sqlite
php artisan migrate --force
```

### Step 4: Strict File Permissions
```bash
sudo chown -R www-data:www-data storage bootstrap/cache database
sudo chmod -R 775 storage bootstrap/cache database
# If SQLite:
sudo chmod 664 database/database.sqlite
```

---

## 4. Web Server & Background Workers

### Local Nginx Virtual Host (Port Isolation)
Give each new local app a dedicated internal listening port (e.g. `8085`, `8086`) in `/etc/nginx/sites-available/<project-name>.conf`:
```nginx
server {
    listen 8085;
    server_name _;
    root /var/www/<project-name>/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
Enable and reload:
```bash
sudo ln -sf /etc/nginx/sites-available/<project-name>.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### Supervisor Queue Worker
In `/etc/supervisor/conf.d/<project-name>-worker.conf`:
```ini
[program:<project-name>-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/<project-name>/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/<project-name>/storage/logs/worker.log
stopwaitsecs=3600
```
Start daemon:
```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all
```

---

## 5. Public Internet & Domain Access Patterns

### Pattern A: Cloudflare Tunnels (Zero Port Forwarding — Recommended for External)
- **Why:** Bypasses ISP CGNAT, ISP port 80/443 blocks, and dual-WAN asymmetric routing drops. Automatic SSL/HTTPS.
- **Workflow:**
  1. Add hostname route in Cloudflare Zero Trust: `subdomain.domain.com` -> `HTTP://localhost:<port>`.
  2. The background `cloudflared` daemon on Ubuntu handles secure tunneling.

### Pattern B: Local LAN & DuckDNS (High Speed LAN Access)
- **Why:** Instant Gigabit local connection without going through external internet.
- **SSL Issuance:** In Nginx Proxy Manager (`192.168.8.19:81`), issue Let's Encrypt certificates using **DNS-01 Challenge** with DuckDNS token (avoids port 80 router firewall dropouts).
- **NAT Loopback Solution:** In AdGuard Home (`192.168.8.19:8088`), add DNS rewrite:
  `<subdomain>.duckdns.org` -> `192.168.8.19`.
