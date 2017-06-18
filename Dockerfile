FROM php:7.1-cli

RUN pecl install trader && docker-php-ext-enable trader

ADD . /app

WORKDIR /app

VOLUME /app/data

VOLUME /app/output

ENTRYPOINT php /app/rate-analizer.php
