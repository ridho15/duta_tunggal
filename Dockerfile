# syntax=docker/dockerfile:1.7

FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY tailwind.config.js ./
COPY postcss.config.cjs ./

RUN npm run build

FROM php:8.3-apache AS app

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=http://localhost \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    DB_CONNECTION=mysql \
    DB_HOST=127.0.0.1 \
    DB_PORT=3306 \
    DB_DATABASE=duta_tunggal \
    DB_USERNAME=duta_tunggal \
    DB_PASSWORD=secret \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        default-mysql-client \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl pcntl pdo_mysql zip \
    && a2enmod expires headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/mysql-client-wrapper/mysql /usr/local/bin/mysql

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . ./
COPY --from=frontend /app/public/build ./public/build
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/duta-tunggal-entrypoint

RUN chmod +x /usr/local/bin/duta-tunggal-entrypoint \
    && chmod +x /usr/local/bin/mysql \
    && rm -f bootstrap/cache/*.php \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts \
    && php artisan package:discover --ansi \
    && php artisan storage:link || true

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=5 CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["duta-tunggal-entrypoint"]
CMD ["apache2-foreground"]
