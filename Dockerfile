FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN printf "upload_max_filesize=1024M\npost_max_size=1100M\nmax_file_uploads=500\nmemory_limit=1024M\nmax_execution_time=900\nmax_input_time=900\n" \
    > /usr/local/etc/php/conf.d/naser.ini

WORKDIR /var/www/html