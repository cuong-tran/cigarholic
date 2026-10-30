# syntax=docker/dockerfile:1.7
# PHP-FPM for Cigarholic: the VPS runs Ubuntu 26.04's PHP 8.5, so this image tracks the 8.5 minor.
ARG PHP_VERSION=8.5.12
FROM php:${PHP_VERSION}-fpm-trixie

ARG HTTPS_PROXY=""
ARG NO_PROXY=""

COPY --from=mlocati/php-extension-installer:2.13.1 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.8.12 /usr/bin/composer /usr/local/bin/composer
COPY --from=wordpress:cli-2.12.0-php8.5 /usr/local/bin/wp /usr/local/bin/wp-cli.phar
COPY --from=axllent/mailpit:v1.32.0 /mailpit /usr/local/bin/mailpit

# Extensions WordPress and WooCommerce use (gd with webp/avif, intl, mysqli, pdo_mysql, zip, bcmath, exif,
# soap, opcache) and the object-cache client phpredis. phpredis is fetched with curl and built with the
# image's own toolchain: PEAR's HTTP/1.0 client gets "426 Upgrade Required" from pecl.php.net. The CA
# secret is only present when building behind the sandbox proxy; it never ends up in a layer.
ARG PHPREDIS_VERSION=6.3.0
RUN --mount=type=secret,id=ca_bundle,target=/run/secrets/ca_bundle \
    set -eux; \
    if [ -s /run/secrets/ca_bundle ]; then \
        cp /run/secrets/ca_bundle /usr/local/share/ca-certificates/build-proxy.crt; \
        update-ca-certificates; \
    fi; \
    export HTTPS_PROXY="$HTTPS_PROXY" https_proxy="$HTTPS_PROXY" NO_PROXY="$NO_PROXY" no_proxy="$NO_PROXY"; \
    install-php-extensions bcmath exif gd intl mysqli opcache pdo_mysql soap zip; \
    curl -fsSL -o /tmp/redis.tgz "https://pecl.php.net/get/redis-${PHPREDIS_VERSION}.tgz"; \
    mkdir -p /usr/src/php/ext/redis; \
    tar -xzf /tmp/redis.tgz -C /usr/src/php/ext/redis --strip-components=1; \
    rm -f /tmp/redis.tgz; \
    docker-php-ext-install -j"$(nproc)" redis; \
    rm -rf /usr/src/php/ext/redis; \
    apt-get update; \
    apt-get install -y --no-install-recommends mariadb-client less unzip git; \
    rm -rf /var/lib/apt/lists/*; \
    if [ -f /usr/local/share/ca-certificates/build-proxy.crt ]; then \
        rm -f /usr/local/share/ca-certificates/build-proxy.crt; \
        update-ca-certificates --fresh; \
    fi

COPY php.ini /usr/local/etc/php/conf.d/zz-cigarholic.ini
COPY www.conf /usr/local/etc/php-fpm.d/zz-cigarholic.conf
COPY entrypoint.sh /usr/local/bin/cigarholic-entrypoint
COPY wp /usr/local/bin/wp
RUN chmod +x /usr/local/bin/cigarholic-entrypoint /usr/local/bin/wp

WORKDIR /var/www/html
ENTRYPOINT ["cigarholic-entrypoint"]
CMD ["php-fpm"]
