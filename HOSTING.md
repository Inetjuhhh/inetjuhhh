# inetjuhhh — server setup

Laravel 13 + Filament 5 blog. You get two files from Ine: `inetjuhhh-database.sql.gz` (full database) and `inetjuhhh-photos.tar` (the photo library, ~820 MB), plus the secret `.env` values. Run on the **temporary Cloudways address**; Ine connects the real domain later herself.

## Requirements
- PHP **8.3+** (developed on 8.4). Extensions: `gd` with **WebP + JPEG**, `exif`, `intl`, `zip`, `fileinfo`, `mbstring`
- MySQL 8 or MariaDB 10.6+ (dump is `utf8mb4_unicode_ci`)
- Node 20+ (Vite build; `public/build` is not in git)
- PHP: `upload_max_filesize = 32M`, `post_max_size = 64M`, `memory_limit = 512M`, `max_execution_time = 300` (photo uploads up to 30 MB are resized server-side)
- ~2 GB disk

## Cloudways
- **Webroot:** `public_html/public`
- **Varnish: off** for this app (sessions/CSRF on every page; the app caches itself)
- **Supervisor job** (Application Settings → Supervisord): `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`
- **Cron** (every minute): `php artisan schedule:run`
- Backups must include `storage/app/public` (the photos live there, not in git)

## Install
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
```

`.env` (secrets from Ine):
```env
APP_NAME="Inetjuhhh - Blogs"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<temporary cloudways address>
APP_TIMEZONE=UTC

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

ADMIN_EMAILS=...            # Ine's admin login; only these addresses get into /admin

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="${APP_NAME}"

ANTHROPIC_API_KEY=...       # optional (AI tag suggestions in the admin)
TURNSTILE_SITE_KEY=...      # optional but wanted (robot check on comments + newsletter)
TURNSTILE_SECRET_KEY=...
```

Database and photos:
```bash
zcat inetjuhhh-database.sql.gz | mysql -u <user> -p <database>
mkdir -p storage/app/public && tar -xf inetjuhhh-photos.tar -C storage/app/public
php artisan storage:link
php artisan migrate --force          # nothing to do now; keeps later deploys in sync
```
**Never** run `db:seed`, `migrate:fresh` or `wordpress:import` on this server: the database is the real content.

Caches:
```bash
php artisan optimize
php artisan filament:optimize
```

## Check
- `/` shows the blogs with photos, `/admin` shows the login
- Old WordPress links must reach Laravel (they redirect, 301). nginx must not answer missing images itself:
  ```bash
  curl -I https://<address>/2019/01/12/jutten-of-jatten/
  curl -I https://<address>/wp-content/uploads/2019/01/Foto-04-01-19-20-43-16-768x1024.jpg
  ```
- Mail: `php artisan tinker --execute="Mail::raw('Test', fn (\$m) => \$m->to('<Ine>')->subject('Test'));"`
- Queue: `php artisan queue:work --once` gives no error; `failed_jobs` stays empty

## Every deploy
```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize && php artisan filament:optimize
php artisan queue:restart
```

## Later: the real domain (Ine)
When `inetjuhhh.nl` points here: domain + SSL in Cloudways, `APP_URL=https://inetjuhhh.nl`, then `php artisan optimize && php artisan queue:restart`. `www` should redirect to the bare domain.
