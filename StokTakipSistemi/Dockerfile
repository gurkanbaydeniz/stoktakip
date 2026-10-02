# StokTakip API — Render için üretim imajı
# (Render'ın native PHP runtime'ı yok; Docker kullanılır.)
FROM php:8.4-cli-alpine

# Çalışma zamanı kütüphaneleri (intl, zip, postgres istemcisi)
RUN apk add --no-cache icu-libs libzip libpq

# PHP eklentileri: pdo_pgsql (veritabanı), intl, bcmath, zip
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl bcmath zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Önce bağımlılıklar (Docker katman önbelleği için)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Uygulama kodu
COPY . .

EXPOSE 10000

ENTRYPOINT ["sh", "docker/entrypoint.sh"]
