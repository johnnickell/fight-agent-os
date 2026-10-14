FROM php:8.5.10-fpm-bookworm

RUN apt-get update \
    && apt-get install --no-install-recommends --yes libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
COPY etc/docker/fpm/local.ini /usr/local/etc/php/conf.d/local.ini
WORKDIR /app
