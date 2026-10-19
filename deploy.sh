#!/usr/bin/env bash
# Puts the latest code from GitHub live on the server (SERVERSTEPS.md step 12).
# Run on the server: cd /var/www/inetjuhhh && ./deploy.sh
set -euo pipefail

cd "$(dirname "$0")"

echo "→ New code"
git pull --ff-only

echo "→ Packages"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Styles and scripts"
npm ci --no-audit --no-fund
npm run build

echo "→ Database"
php artisan migrate --force

echo "→ Caches"
php artisan optimize
php artisan filament:optimize

echo "→ PHP and the queue worker pick up the new code"
sudo systemctl reload php8.4-fpm
php artisan queue:restart

echo "✓ Done"
