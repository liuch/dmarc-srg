FROM php:8.4-fpm-alpine

ARG VERSION="dev"
ARG BUILD_DATE
ARG VCS_REF

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    nginx \
    tini \
    curl \
    libzip \
    libxml2 \
    icu \
    oniguruma \
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libzip-dev \
    libxml2-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    mbstring \
    xml \
    zip \
    opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/pear

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy runtime configurations (except php.ini — applied after build to avoid open_basedir issues)
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set working directory and copy application
WORKDIR /var/www/dmarc-srg
COPY --chown=www-data:www-data . .

# Install PHP dependencies
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-cache \
    && rm -rf /root/.composer/cache

# Apply production PHP settings only after build steps are complete
COPY docker/php.ini /usr/local/etc/php/conf.d/99-dmarc-srg.ini

# Ensure correct permissions
RUN chown -R www-data:www-data /var/www/dmarc-srg \
    && chmod -R u=rwX,g=rX,o=rX /var/www/dmarc-srg

# Ensure runtime directories are writable by www-data
# Alpine's nginx package defaults to the 'nginx' user, but we run as www-data.
RUN mkdir -p /run/php /run/nginx /var/lib/nginx/tmp /var/cache/nginx /var/log/nginx \
    && chown -R www-data:www-data /run/php /run/nginx /var/lib/nginx /var/cache/nginx /var/log/nginx

LABEL org.opencontainers.image.title="dmarc-srg" \
      org.opencontainers.image.description="A php parser, viewer and summary report generator for incoming DMARC reports." \
      org.opencontainers.image.source="https://github.com/liuch/dmarc-srg" \
      org.opencontainers.image.url="https://github.com/liuch/dmarc-srg#readme" \
      org.opencontainers.image.licenses="GPL-3.0" \
      org.opencontainers.image.version=${VERSION} \
      org.opencontainers.image.created=${BUILD_DATE} \
      org.opencontainers.image.revision=${VCS_REF}

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -fsS http://127.0.0.1:8080/healthz.php >/dev/null || exit 1

USER www-data

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["/usr/local/bin/entrypoint.sh"]
