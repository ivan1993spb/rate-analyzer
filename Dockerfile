FROM php:7.1-cli

ADD . /app

WORKDIR /app

VOLUME /app/data

ENTRYPOINT php /app/rate-analizer.php
