FROM php:8.1-cli-alpine

RUN apk add --no-cache bash

RUN  --mount=type=bind,from=mlocati/php-extension-installer:latest,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions mongodb-1.20.0 xdebug @composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY ./conf.d $PHP_INI_DIR/conf.d/

WORKDIR /app


