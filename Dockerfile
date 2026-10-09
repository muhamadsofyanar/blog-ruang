FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring gd zip curl \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*
COPY docker/apache.conf /etc/apache2/conf-available/averion.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/averion.ini
RUN a2enconf averion
WORKDIR /var/www/html
COPY . .
RUN chmod +x docker/entrypoint.sh
ENV AVERION_RUNTIME_DIR=/var/lib/averion AVERION_CONTAINER=1
EXPOSE 80
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
