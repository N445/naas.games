# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1.12-php8.4 AS base
WORKDIR /app
ARG APP_UID=1000
ARG APP_GID=1000
RUN install-php-extensions intl opcache zip pdo_mysql apcu imagick exif
RUN apt-get update && apt-get install -y --no-install-recommends supervisor \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer/composer:2-bin /composer /usr/bin/composer
COPY docker/php/conf.d/base.ini $PHP_INI_DIR/conf.d/
RUN groupmod -g $APP_GID www-data \
 && usermod -u $APP_UID -g $APP_GID -s /bin/bash -d /home/www-data www-data \
 && install -d -o www-data -g www-data /home/www-data
# La prod monte HOME sur /tmp : un ~/.bashrc livré dans l'image n'y serait jamais lu.
COPY docker/php/bashrc /etc/naas-games.bashrc
RUN echo '. /etc/naas-games.bashrc' >> /etc/bash.bashrc
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]

FROM base AS dev
ENV APP_ENV=dev \
    XDEBUG_MODE=off \
    HOME=/home/www-data \
    XDG_DATA_HOME=/tmp \
    XDG_CONFIG_HOME=/tmp \
    COMPOSER_HOME=/app/var/composer \
    YARN_CACHE_FOLDER=/app/var/yarn
RUN install-php-extensions xdebug
COPY docker/php/conf.d/dev.ini $PHP_INI_DIR/conf.d/
COPY docker/supervisor/dev/supervisord.conf /etc/supervisor/supervisord.conf
RUN apt-get update && apt-get install -y --no-install-recommends git \
 && rm -rf /var/lib/apt/lists/*
ARG TARGETARCH
ARG SYMFONY_CLI_VERSION=5.20.0
RUN curl -sSLf "https://github.com/symfony-cli/symfony-cli/releases/download/v${SYMFONY_CLI_VERSION}/symfony-cli_linux_${TARGETARCH}.tar.gz" \
      | tar -xz -C /usr/local/bin symfony
COPY --from=node:22-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-trixie-slim /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && npm install -g yarn@1.22.22

FROM base AS builder
ENV APP_ENV=prod APP_DEBUG=0
COPY composer.* symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative
# --no-scripts saute assets:install : sans elle EasyAdmin sert ses assets en 404.
RUN bin/console assets:install public --no-interaction

FROM node:22-trixie-slim AS assets
WORKDIR /app
COPY --from=builder /app/vendor ./vendor
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile
COPY webpack.config.js postcss.config.mjs ./
COPY assets ./assets
RUN yarn build

FROM base AS prod
ARG VERSION=dev
ARG REVISION
LABEL org.opencontainers.image.source="https://github.com/N445/naas.games" \
      org.opencontainers.image.title="Naas Games" \
      org.opencontainers.image.description="Site Naas Games — Symfony 8 + FrankenPHP" \
      org.opencontainers.image.licenses="UNLICENSED" \
      org.opencontainers.image.version="$VERSION" \
      org.opencontainers.image.revision="$REVISION"
ENV APP_ENV=prod APP_DEBUG=0 FRANKENPHP_CONFIG="worker ./public/index.php"
COPY docker/php/conf.d/prod.ini $PHP_INI_DIR/conf.d/
COPY docker/supervisor/prod/supervisord.conf /etc/supervisor/supervisord.conf
COPY --from=builder /app /app
COPY --from=assets /app/public/build /app/public/build
