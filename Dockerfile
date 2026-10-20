# ==========================================
# DroneHub Australia — Backend Deploy Image
# Railway (Dockerfile builder) + local docker build
# PHP 8.3 FPM + Nginx + Supervisor in a single container
# NOTE: The React frontend lives in its own repository
#       (DroneHub-Frontend) and is NOT built here.
# ==========================================

# ---------- 1. PHP dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader \
      --no-scripts

# ---------- 2. Production runtime ----------
FROM php:8.3-fpm-alpine

# System packages (nginx, supervisor, PHP build libs)
RUN apk add --no-cache \
      nginx \
      supervisor \
      curl \
      libpng-dev \
      libxml2-dev \
      zip \
      unzip \
      oniguruma-dev \
      libzip-dev \
      freetype-dev \
      libjpeg-turbo-dev \
      libwebp-dev \
      icu-dev \
      linux-headers

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Redis extension (cache / session / queue)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# ---------- 3. Service configs ----------
COPY docker/nginx/templates/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# ---------- 4. Application ----------
WORKDIR /var/www
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Runtime directories Laravel needs (storage + caches)
RUN mkdir -p \
      /var/www/storage/framework/cache/data \
      /var/www/storage/framework/sessions \
      /var/www/storage/framework/views \
      /var/www/storage/logs \
      /var/www/bootstrap/cache \
      /var/www/public/build \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
