#!/usr/bin/env bash
# Termius/SSH deploy — laravel/ papkada ishga tushiriladi: bash deploy.sh
#
# Frontend serverda BUILD QILINMAYDI — tayyor build public/ ichida repo bilan keladi.
# Migratsiyalar QO'SHIMCHA (additive): `migrate --force`, hech qachon `migrate:fresh`.
set -e

cd "$(dirname "$0")"

echo "▸ Kod tortilyapti..."
git pull

echo "▸ Composer paketlari..."
composer install --no-dev --optimize-autoloader

echo "▸ Migratsiya (mavjud ma'lumot saqlanadi)..."
php artisan migrate --force

echo "▸ Kesh yangilanmoqda..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "▸ Ruxsatlar..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# --- Telegram sozlamalari (tarmoq uzilsa deploy to'xtamasin) ---

echo "▸ Telegram webhook..."
if php artisan battle:set-webhook >/dev/null 2>&1; then
  echo "  ✓ o'rnatildi"
else
  echo "  ⚠ o'tkazib yuborildi — keyin qo'lda: php artisan battle:set-webhook"
  echo "    (webhook'siz /start va taklif havolalari ISHLAMAYDI)"
fi

echo "▸ Telegram menyu tugmasi..."
if php artisan battle:set-menu >/dev/null 2>&1; then
  echo "  ✓ o'rnatildi"
else
  echo "  ⚠ o'tkazib yuborildi — keyin qo'lda: php artisan battle:set-menu"
fi

# --- Cron tekshiruvi: ballar va missiya natijasi shunga bog'liq ---
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
  echo "▸ Cron: ✓ sozlangan"
else
  echo ""
  echo "  ⚠ DIQQAT: cron sozlanmagan. Usiz kunlik yopish ishlamaydi —"
  echo "    o'tkazib yuborilgan kunlar, duel g'olibi va missiya natijasi hisoblanmaydi."
  echo "    Bir marta qo'shing (crontab -e):"
  echo ""
  echo "    * * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"
  echo ""
fi

echo "✅ Deploy tayyor."
