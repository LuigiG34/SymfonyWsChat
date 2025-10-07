# Pin to latest Alpine track for security updates
FROM php:8.3-fpm-alpine3.22

ARG APP_ENV=dev

# Runtime libs only (no -dev headers stay in final image)
RUN apk add --no-cache icu-libs libzip libpng libxml2 libpq

# Build the PHP extensions, then remove build deps to shrink attack surface
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libxml2-dev postgresql-dev; \
    docker-php-ext-install -j"$(nproc)" intl pdo_mysql pdo_pgsql opcache; \
    apk del .build-deps

# Dev tools only when APP_ENV=dev
RUN if [ "$APP_ENV" = "dev" ]; then apk add --no-cache bash git unzip; fi

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# Opcache (dev-friendly)
RUN { \
  echo 'opcache.enable=1'; \
  echo 'opcache.enable_cli=1'; \
  echo 'opcache.validate_timestamps=1'; \
  echo 'opcache.revalidate_freq=0'; \
} > /usr/local/etc/php/conf.d/opcache.ini
