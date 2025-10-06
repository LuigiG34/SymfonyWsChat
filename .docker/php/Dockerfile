FROM php:8.3-fpm-alpine


# System deps
RUN apk add --no-cache bash git unzip icu-dev oniguruma-dev libzip-dev libpng-dev libxml2-dev


# PHP extensions (common stack)
RUN docker-php-ext-install intl pdo pdo_mysql pdo_pgsql opcache


# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


WORKDIR /var/www/html


# Speed up Composer inside container
ENV COMPOSER_ALLOW_SUPERUSER=1 \
COMPOSER_NO_INTERACTION=1


# Opcache recommended defaults for dev
RUN { \
echo 'opcache.enable=1'; \
echo 'opcache.enable_cli=1'; \
echo 'opcache.validate_timestamps=1'; \
echo 'opcache.revalidate_freq=0'; \
} > /usr/local/etc/php/conf.d/opcache.ini