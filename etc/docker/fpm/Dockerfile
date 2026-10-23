FROM php:8.5.12-fpm-bookworm@sha256:d3db5340c7ecfb1e3a26af5d3d9de8b4e160cdd75692a830b5cb74b0b85db2cf

RUN apt-get update \
    && apt-get install --no-install-recommends --yes ca-certificates libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && pecl install xdebug-3.5.0 \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*
COPY etc/docker/php/development.ini /usr/local/etc/php/conf.d/zz-development.ini
COPY etc/docker/fpm/local.ini /usr/local/etc/php/conf.d/zzz-fpm-local.ini
COPY etc/docker/fpm/zz-local.conf /usr/local/etc/php-fpm.d/zz-local.conf
WORKDIR /app
