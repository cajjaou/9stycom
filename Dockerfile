FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install -j"$(nproc)" curl fileinfo mbstring mysqli pdo_mysql \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && mkdir -p /var/www/html/cache /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/cache /var/www/html/uploads

RUN printf '%s\n' \
    'upload_max_filesize=64M' \
    'post_max_size=70M' \
    'max_execution_time=600' \
    'memory_limit=512M' \
    > /usr/local/etc/php/conf.d/zz-site.ini

COPY . /var/www/html/

EXPOSE 80
