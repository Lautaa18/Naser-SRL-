FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo_mysql zip opcache \
    && a2enmod rewrite headers deflate expires \
    && rm -rf /var/lib/apt/lists/*

RUN printf "upload_max_filesize=1024M\npost_max_size=1100M\nmax_file_uploads=500\nmemory_limit=1024M\nmax_execution_time=900\nmax_input_time=900\n" \
    > /usr/local/etc/php/conf.d/naser.ini

# Cache de PHP compilado: sin esto PHP vuelve a leer y compilar todos los archivos en CADA pedido (muy lento sobre las carpetas de Windows)
RUN printf "opcache.enable=1\nopcache.memory_consumption=128\nopcache.interned_strings_buffer=16\nopcache.max_accelerated_files=10000\nopcache.validate_timestamps=1\nopcache.revalidate_freq=2\nrealpath_cache_size=4096K\nrealpath_cache_ttl=600\n" \
    > /usr/local/etc/php/conf.d/zz-opcache.ini

# Habilita los .htaccess y oculta la version del servidor
COPY docker/apache-naser.conf /etc/apache2/conf-available/zz-naser.conf
RUN a2enconf zz-naser

WORKDIR /var/www/html
