FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

COPY index.php /var/www/html/index.php
COPY config.php /var/www/html/config.php
COPY config-editor.php /var/www/html/config-editor.php

RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chown www-data:www-data /var/www/html/config.php

EXPOSE 80
