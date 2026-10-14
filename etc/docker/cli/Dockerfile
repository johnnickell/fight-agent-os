# Acceptance tooling is installed only during explicit image setup, never by bin/build.
FROM node@sha256:0e0ff40c39bc087845bfb27465a0df4ea419520094bc35842ff83dd8cbe6f9b6 AS node
FROM mcr.microsoft.com/playwright@sha256:57b65fdc9ceabe0ef613124c7bbe2babcf9362c4d85e382fe3b03604e84b428a AS browser
FROM php:8.5-cli-bookworm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
COPY --from=browser /ms-playwright/chromium-1228 /ms-playwright/chromium-1228
COPY --from=browser /ms-playwright/chromium_headless_shell-1228 /ms-playwright/chromium_headless_shell-1228
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && apt-get update \
    && apt-get install --no-install-recommends --yes git libpq-dev python3 unzip \
        libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libdbus-1-3 \
        libx11-6 libxcomposite1 libxdamage1 libxext6 libxfixes3 libxrandr2 \
        libgbm1 libxcb1 libxkbcommon0 libasound2 libpango-1.0-0 libcairo2 \
        fonts-liberation fonts-noto-color-emoji \
    && docker-php-ext-install pdo_pgsql \
    && pecl install xdebug-3.5.0 \
    && docker-php-ext-enable xdebug \
    && printf 'xdebug.mode=off\n' > /usr/local/etc/php/conf.d/zz-xdebug-mode.ini \
    && rm -rf /var/lib/apt/lists/*
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
COPY etc/docker/cli/Dockerfile /usr/local/share/fight-build/Dockerfile
WORKDIR /app
