# PHP 8.6 CLI plus Composer. Image tag is recorded at runtime via `php -v`.
FROM php:8.6-rc-cli

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /work/content/flow-partial-function-application
