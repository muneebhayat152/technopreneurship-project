# Railway / Docker: PHP + Composer + Laravel (API only)
FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize

ENV PORT=8080
EXPOSE 8080

# Fallback if Railway overrides CMD — migrations run via railway.json startCommand
CMD sh -c "php artisan migrate --force && php -S 0.0.0.0:${PORT} -t public"
