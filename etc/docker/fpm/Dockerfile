FROM php:8.5.10-fpm-bookworm

RUN apt-get update \
    && apt-get install --no-install-recommends --yes libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && pecl install xdebug-3.5.0 \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*
COPY etc/docker/php/development.ini /usr/local/etc/php/conf.d/zz-development.ini
COPY etc/docker/fpm/local.ini /usr/local/etc/php/conf.d/zzz-fpm-local.ini
COPY etc/docker/fpm/zz-local.conf /usr/local/etc/php-fpm.d/zz-local.conf
WORKDIR /app
