FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --ignore-platform-req=ext-redis --no-scripts

FROM php:8.4-cli-alpine
RUN apk add --no-cache postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
ENV APP_ENV=prod APP_DEBUG=0
RUN APP_SECRET=build-only-secret php bin/console importmap:install --env=prod \
    && APP_SECRET=build-only-secret php bin/console cache:clear --env=prod
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
