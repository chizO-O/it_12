# GroveMap: Render Free single-container Laravel web service.
# Frontend assets are compiled during the image build; no Vite server in production.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/
RUN npm ci --no-audit --no-fund && npm run build

FROM php:8.3-apache
ENV PORT=10000 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libonig-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring intl zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .

# Use the PHP runtime's installed extensions to validate Composer's requirements.
# No database migrations and no APP_KEY generation occur during image build.
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts \
    && mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/grovemap-apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/start-grovemap.sh /usr/local/bin/start-grovemap
RUN printf 'Listen 10000\n' > /etc/apache2/ports.conf \
    && chmod +x /usr/local/bin/start-grovemap \
    && chown -R www-data:www-data public/build

EXPOSE 10000
CMD ["/usr/local/bin/start-grovemap"]
