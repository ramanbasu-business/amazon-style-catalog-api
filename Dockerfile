# syntax=docker/dockerfile:1

FROM php:8.3-cli-alpine AS base

RUN docker-php-ext-install pdo_mysql opcache \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies are installed before the source is copied, so a code change does
# not invalidate the vendor layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist \
    && composer clear-cache

COPY . .

# The app runs as a non-root user; nothing in the image needs write access.
RUN addgroup -S app && adduser -S -G app app && chown -R app:app /app
USER app

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]


FROM base AS dev

USER root
RUN composer install --no-interaction --prefer-dist && composer clear-cache && chown -R app:app /app
USER app
