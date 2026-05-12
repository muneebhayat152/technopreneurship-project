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

# php -S -t public does NOT route /api to Laravel (404). Use artisan serve.
CMD sh -c "php artisan migrate --force && php artisan db:seed --force --class=PlatformOwnersSeeder && php artisan serve --host=0.0.0.0 --port=${PORT}"
