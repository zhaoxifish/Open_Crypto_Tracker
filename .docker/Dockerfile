FROM php:8.3-apache-bookworm@sha256:b79a53c3fb1dd4b9f350a8d99d84951512b6c21044e61bbecfee8af030b3722e

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       ca-certificates curl openssl util-linux \
       libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mysqli pdo_mysql zip \
    && php -r 'foreach (["curl", "dom", "gd", "mbstring", "mysqli", "pdo_mysql", "SimpleXML", "xml", "zip"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing extension: $extension\n"); exit(1); } } if (!function_exists("imagettftext")) { exit(1); }' \
    && a2enmod rewrite ssl headers \
    && a2dissite 000-default \
    && rm -rf /var/lib/apt/lists/*

COPY .docker/apache-ports.conf /etc/apache2/ports.conf
COPY .docker/apache-local.conf /etc/apache2/sites-available/oct-local.conf
COPY .docker/php-local.ini /usr/local/etc/php/conf.d/zz-oct-local.ini
COPY .docker/entrypoint.sh /usr/local/bin/oct-entrypoint
COPY .docker/cron-loop.sh /usr/local/bin/oct-cron-loop

RUN chmod 755 /usr/local/bin/oct-entrypoint /usr/local/bin/oct-cron-loop \
    && a2ensite oct-local

WORKDIR /var/www/html
EXPOSE 80 8443
ENTRYPOINT ["oct-entrypoint"]
CMD ["apache2-foreground"]
