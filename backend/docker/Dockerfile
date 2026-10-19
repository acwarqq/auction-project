FROM php:8.4-fpm

# System libraries + PHP extensions (phpredis via pecl; the rest via docker-php-ext-install)
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libpq-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pcntl \
        posix \
        intl \
        zip \
        bcmath \
        gd \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer inside the image (used by `docker compose exec backend composer ...`)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY zz-auction.conf /usr/local/etc/php-fpm.d/zz-auction.conf

WORKDIR /var/www

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
