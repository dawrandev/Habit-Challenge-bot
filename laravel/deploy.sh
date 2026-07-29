#!/usr/bin/env bash
# Termius/SSH deploy — laravel/ papkada ishga tushiriladi: bash deploy.sh
set -e

echo "▸ Kod tortilyapti..."
git pull

echo "▸ Composer paketlari..."
composer install --no-dev --optimize-autoloader

echo "▸ Migratsiya..."
php artisan migrate --force

echo "▸ Kesh..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "▸ Ruxsatlar..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "▸ Telegram menyu tugmasi..."
php artisan battle:set-menu || true

echo "✅ Deploy tayyor."
