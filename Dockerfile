# syntax=docker/dockerfile:1

# --- Build stage: install dependencies and compile assets ---
FROM php:8.4-cli AS builder

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev unzip \
    && docker-php-ext-install intl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
ENV APP_ENV=prod \
    APP_DEBUG=0

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .

# AssetMapper needs no Node/npm toolchain: the Tailwind CLI binary is downloaded
# on demand and asset-map:compile writes versioned files straight to public/assets.
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && php bin/console tailwind:build --no-interaction \
    && php bin/console asset-map:compile --no-interaction \
    && php bin/console cache:warmup

# --- Runtime stage: lean PHP-FPM image, no build tooling ---
FROM php:8.4-fpm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev \
    && docker-php-ext-install pdo_mysql intl opcache \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.preload=/app/config/preload.php'; \
        echo 'opcache.preload_user=www-data'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

WORKDIR /app
ENV APP_ENV=prod \
    APP_DEBUG=0

COPY --from=builder --chown=www-data:www-data /app /app

USER www-data

EXPOSE 9000
CMD ["php-fpm"]

# --- Nginx stage: serves static assets directly, proxies PHP requests to the runtime stage ---
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=builder /app/public /app/public

EXPOSE 80
