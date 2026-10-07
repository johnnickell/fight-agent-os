FROM php:8.5-cli-bookworm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update \
    && apt-get install --no-install-recommends --yes git libpq-dev python3 unzip \
    && docker-php-ext-install pdo_pgsql \
    && pecl install xdebug-3.5.0 \
    && docker-php-ext-enable xdebug \
    && printf 'xdebug.mode=off\n' > /usr/local/etc/php/conf.d/zz-xdebug-mode.ini \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
