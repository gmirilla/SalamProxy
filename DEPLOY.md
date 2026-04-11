# SalamProxy — Deployment Guide

SalamProxy sits on a Nigerian VPS and forwards API calls that Namecheap (US-hosted)
cannot reach directly (e.g. NPF eCMR at cmrapp.npf.gov.ng).

---

## 1. VPS Requirements

| Item | Minimum |
|------|---------|
| Location | **Nigeria** (HostNowNow, QServers, Whogohost) |
| OS | Ubuntu 22.04 LTS |
| RAM | 512 MB |
| PHP | 8.2 |

---

## 2. Server Setup (run as root or sudo user)

```bash
# System packages
apt update && apt install -y nginx php8.2-fpm php8.2-curl php8.2-mbstring \
  php8.2-xml php8.2-zip unzip git

# Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

---

## 3. Deploy the App

```bash
cd /var/www
git clone <your-repo-url> SalamProxy   # or upload via SFTP
cd SalamProxy

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

---

## 4. Configure .env

Open `.env` and fill in:

```env
APP_URL=https://proxy.yourdomain.com
APP_ENV=production
APP_DEBUG=false

PROXY_SECRET=          # generate below — must match SalamOnline .env
eMCR_URL=https://cmrapp.npf.gov.ng/
eMCR_USERNAME=niip_admin@salamtakafulinsurance.com
eMCR_PASSWORD=McmG2018*
```

**Generate the shared secret** (run once, copy to both apps):
```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

---

## 5. File Permissions

```bash
chown -R www-data:www-data /var/www/SalamProxy
chmod -R 775 /var/www/SalamProxy/storage /var/www/SalamProxy/bootstrap/cache
```

---

## 6. Nginx Config

Create `/etc/nginx/sites-available/salamproxy`:

```nginx
server {
    listen 80;
    server_name proxy.yourdomain.com;
    root /var/www/SalamProxy/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/salamproxy /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

---

## 7. SSL Certificate (free, auto-renews)

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d proxy.yourdomain.com
```

---

## 8. Configure SalamOnline (Namecheap)

Add to `SalamOnline/.env`:

```env
PROXY_URL=https://proxy.yourdomain.com
PROXY_SECRET=          # same value as proxy .env
```

Then update `EcmrController.php` — replace the direct NPF calls with:

```php
$proxyHttp = Http::withHeaders(['X-Proxy-Secret' => env('PROXY_SECRET')])->timeout(30);

// Login
$response   = $proxyHttp->post(env('PROXY_URL') . '/api/ecmr/login');
$jsonObject = json_decode($response->body());

// Lookup
$querysearch = $proxyHttp->get(env('PROXY_URL') . '/api/ecmr/lookup', [
    'token' => $jsonObject->data->token,
    'regno' => $ecmr_check,
]);
```

---

## 9. Test It

```bash
# From any machine — should return a token JSON
curl -s -X POST https://proxy.yourdomain.com/api/ecmr/login \
  -H "X-Proxy-Secret: your-secret-here"
```

A `401` means wrong secret. A `502` means the proxy reached the VPS but NPF is still
unreachable. A `200` with a token means everything is working.

---

## Adding More Blocked APIs

1. Add a method to `app/Http/Controllers/ProxyController.php`
2. Add a route to `routes/api.php` inside the `proxy.auth` middleware group
3. Add the API credentials to `.env` on the proxy server
4. Call the new proxy route from SalamOnline instead of the blocked API directly
