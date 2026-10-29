FROM php:8.5.12-fpm-bookworm@sha256:d3db5340c7ecfb1e3a26af5d3d9de8b4e160cdd75692a830b5cb74b0b85db2cf

RUN apt-get update \
    && apt-get install --no-install-recommends --yes ca-certificates libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && curl --fail --silent --show-error --location https://xdebug.org/files/xdebug-3.5.0.tgz --output /tmp/xdebug.tgz \
    && echo 'f6daf55a5c7adadb07dd2af25ff78b1cc9b1c58d6cc442a463eba015b678aabf  /tmp/xdebug.tgz' | sha256sum --check --strict \
    && mkdir /tmp/xdebug \
    && tar --extract --gzip --file /tmp/xdebug.tgz --directory /tmp/xdebug --strip-components=1 \
    && (cd /tmp/xdebug && phpize && ./configure --enable-xdebug && make -j"$(nproc)" && make install) \
    && docker-php-ext-enable xdebug \
    && rm -rf /tmp/xdebug /tmp/xdebug.tgz /var/lib/apt/lists/*
COPY etc/docker/php/development.ini /usr/local/etc/php/conf.d/zz-development.ini
COPY etc/docker/fpm/local.ini /usr/local/etc/php/conf.d/zzz-fpm-local.ini
COPY etc/docker/fpm/zz-local.conf /usr/local/etc/php-fpm.d/zz-local.conf
WORKDIR /app
