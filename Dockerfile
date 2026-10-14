FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --ignore-platform-req=ext-*

FROM node:22-bookworm-slim AS node

# O plugin Vite do Wayfinder executa `php artisan wayfinder:generate` durante o build, então o estágio precisa de PHP e Node.
FROM php:8.4-cli AS frontend
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN php artisan package:discover --ansi && npm run build

# Estágio final (padrão do `docker compose build`): PHP-FPM, worker e scheduler.
FROM php:8.4-fpm
RUN apt-get update && apt-get install -y --no-install-recommends git curl libpng-dev libonig-dev libxml2-dev libzip-dev libicu-dev zip unzip && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction && mkdir -p /opt/public-build storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /app/public/build /opt/public-build
COPY docker-entrypoint.sh /usr/local/bin/infra-entrypoint
RUN chmod +x /usr/local/bin/infra-entrypoint
ENTRYPOINT ["infra-entrypoint"]
CMD ["php-fpm", "-F"]
