FROM php:7.1-cli

RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -yq zlib1g-dev

RUN pecl install trader && docker-php-ext-enable trader; docker-php-ext-install -j$(nproc) zip

ADD . /app
