FROM php:8.5-cli-bookworm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install --no-install-recommends --yes git python3 unzip && rm -rf /var/lib/apt/lists/*
WORKDIR /app
