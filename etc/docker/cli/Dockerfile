# Acceptance tooling is installed only during explicit image setup, never by bin/build.
FROM node@sha256:55fb5f5a527ea2c5c4a95f963e48ef90aa38c7b346054fdc8ee9957a2b55d205 AS node
FROM mcr.microsoft.com/playwright@sha256:57b65fdc9ceabe0ef613124c7bbe2babcf9362c4d85e382fe3b03604e84b428a AS browser
FROM php:8.5.12-cli-bookworm@sha256:d4f6e10eb393de7e3a7a88e8b88e139072dd3ae297f6a5dada8257e6fe32c585

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
COPY --from=browser /ms-playwright/chromium-1228 /ms-playwright/chromium-1228
COPY --from=browser /ms-playwright/chromium_headless_shell-1228 /ms-playwright/chromium_headless_shell-1228
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && apt-get update \
    && apt-get install --no-install-recommends --yes ca-certificates git libpq-dev python3 unzip \
        libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libdbus-1-3 \
        libx11-6 libxcomposite1 libxdamage1 libxext6 libxfixes3 libxrandr2 \
        libgbm1 libxcb1 libxkbcommon0 libasound2 libpango-1.0-0 libcairo2 \
        fonts-liberation fonts-noto-color-emoji \
    && docker-php-ext-install pdo_pgsql \
    && pecl install xdebug-3.5.0 \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
COPY etc/docker/cli/Dockerfile /usr/local/share/fight-build/Dockerfile
COPY etc/docker/php/development.ini /usr/local/etc/php/conf.d/zz-development.ini
WORKDIR /app
