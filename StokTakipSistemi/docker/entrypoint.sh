#!/bin/sh
# Render konteyner başlangıcı: env hazırken cache + migrate + sunucu
set -e
cd /var/www/html

# APP_KEY verilmediyse üret (her restart yeni üretilebilir; Sanctum token'ları
# ve bcrypt şifreleri APP_KEY'e bağlı değildir).
if [ -z "${APP_KEY:-}" ]; then
  APP_KEY=$(php artisan key:generate --show)
  export APP_KEY
fi

php artisan config:cache
php artisan route:cache
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
