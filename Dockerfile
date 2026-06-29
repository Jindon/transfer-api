FROM php:8.5-cli-alpine

RUN apk add --no-cache icu-dev \
    && docker-php-ext-install pdo_mysql intl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts

COPY . .

EXPOSE 8000

ENTRYPOINT ["sh", "docker/entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public/"]
