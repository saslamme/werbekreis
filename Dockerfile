# syntax=docker/dockerfile:1
FROM composer:2 AS composer
FROM php:8.4-cli-bookworm
RUN --mount=type=secret,id=proxy_ca,target=/run/proxy-ca.pem \
    apt-get -o Acquire::https::CaInfo=/run/proxy-ca.pem update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install intl pdo_mysql zip && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --chmod=0644 docker/php/uploads.ini /usr/local/etc/php/conf.d/werbekreis-uploads.ini
COPY --chmod=0644 docker/php/cloud-ca.ini /usr/local/etc/php/conf.d/werbekreis-cloud-ca.ini
WORKDIR /app
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
