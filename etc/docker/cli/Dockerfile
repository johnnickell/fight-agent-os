# Acceptance tooling is installed only during explicit image setup, never by bin/build.
FROM node@sha256:55fb5f5a527ea2c5c4a95f963e48ef90aa38c7b346054fdc8ee9957a2b55d205 AS node
FROM mcr.microsoft.com/playwright@sha256:57b65fdc9ceabe0ef613124c7bbe2babcf9362c4d85e382fe3b03604e84b428a AS browser
FROM php:8.5.12-fpm-bookworm@sha256:d3db5340c7ecfb1e3a26af5d3d9de8b4e160cdd75692a830b5cb74b0b85db2cf AS fpm
# Git 2.56.1 fixes the November 2026 Git security advisories. Debian's older
# packages are still affected; build the verified upstream release on the same
# base as the runtime so its HTTPS helper uses compatible shared libraries.
FROM php:8.5.12-cli-bookworm@sha256:d4f6e10eb393de7e3a7a88e8b88e139072dd3ae297f6a5dada8257e6fe32c585 AS git-build
RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        build-essential ca-certificates curl libcurl4-openssl-dev libexpat1-dev \
        libpcre2-dev libssl-dev perl xz-utils zlib1g-dev \
    && curl --fail --silent --show-error --location \
        https://www.kernel.org/pub/software/scm/git/git-2.56.1.tar.xz --output /tmp/git.tar.xz \
    && echo 'f0b0bf89264b9bd2e1c640363e7529643989da76281600473c50745cd34a607f  /tmp/git.tar.xz' | sha256sum --check --strict \
    && tar --extract --xz --file /tmp/git.tar.xz --directory /tmp \
    && make -C /tmp/git-2.56.1 -j2 prefix=/usr/local \
        NO_RUST=YesPlease NO_GETTEXT=YesPlease NO_TCLTK=YesPlease USE_LIBPCRE2=YesPlease all \
    && make -C /tmp/git-2.56.1 prefix=/usr/local \
        NO_RUST=YesPlease NO_GETTEXT=YesPlease NO_TCLTK=YesPlease USE_LIBPCRE2=YesPlease \
        DESTDIR=/opt/git install

FROM php:8.5.12-cli-bookworm@sha256:d4f6e10eb393de7e3a7a88e8b88e139072dd3ae297f6a5dada8257e6fe32c585

COPY --from=git-build /opt/git/usr/local/ /usr/local/

COPY --from=composer:2.11.2@sha256:5bcb28b8d0edc4091125f8218f9d088f998bdfc843f8fbb9a4beb4dcd8625684 /usr/bin/composer /usr/bin/composer
COPY --from=fpm /usr/local/sbin/php-fpm /usr/local/sbin/php-fpm
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
COPY --from=browser /ms-playwright/chromium-1228 /ms-playwright/chromium-1228
COPY --from=browser /ms-playwright/chromium_headless_shell-1228 /ms-playwright/chromium_headless_shell-1228
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && apt-get update \
    && apt-get install --no-install-recommends --yes ca-certificates curl libexpat1 libpcre2-8-0 openssh-client perl zlib1g libpq-dev python3 unzip \
        libnss3 libnss3-tools openssl libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libdbus-1-3 \
        libx11-6 libxcomposite1 libxdamage1 libxext6 libxfixes3 libxrandr2 \
        libgbm1 libxcb1 libxkbcommon0 libasound2 libpango-1.0-0 libcairo2 \
        fonts-liberation fonts-noto-color-emoji \
    && docker-php-ext-install pdo_pgsql \
    && curl --fail --silent --show-error --location https://xdebug.org/files/xdebug-3.5.0.tgz --output /tmp/xdebug.tgz \
    && echo 'f6daf55a5c7adadb07dd2af25ff78b1cc9b1c58d6cc442a463eba015b678aabf  /tmp/xdebug.tgz' | sha256sum --check --strict \
    && mkdir /tmp/xdebug \
    && tar --extract --gzip --file /tmp/xdebug.tgz --directory /tmp/xdebug --strip-components=1 \
    && (cd /tmp/xdebug && phpize && ./configure --enable-xdebug && make -j"$(nproc)" && make install) \
    && docker-php-ext-enable xdebug \
    && rm -rf /tmp/xdebug /tmp/xdebug.tgz /var/lib/apt/lists/*
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
COPY etc/docker/php/development.ini /usr/local/etc/php/conf.d/zz-development.ini
COPY etc/docker/php/runtime-secrets.php /usr/local/etc/php/runtime-secrets.php
COPY etc/docker/php/runtime-secrets.ini /usr/local/etc/php/conf.d/zz-runtime-secrets.ini
WORKDIR /app

# Preserve the distro executable path without retaining its affected Git package.
# A package that reintroduces /usr/bin/git makes this layer fail closed.
RUN ln -s /usr/local/bin/git /usr/bin/git \
    && test "$(git --version)" = 'git version 2.56.1' \
    && test "$(/usr/bin/git --version)" = 'git version 2.56.1' \
    && test "$(readlink /usr/bin/git)" = /usr/local/bin/git \
    && test -x "$(git --exec-path)/git-remote-https"
