# ==============================================================================
# Kaamly Backend API - PHP 8.3-FPM Production-Ready Dockerfile
# Compatible with Laravel 13
# ==============================================================================
FROM php:8.3-fpm-alpine

# Set build-time metadata
LABEL maintainer="Kaamly DevOps <devops@kaamly.com>"
LABEL description="Production PHP 8.3-FPM runtime for Kaamly Laravel 13 API, Reverb & Queue Workers"

# Install production system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    linux-headers \
    fcgi \
    shadow

# Configure and install core PHP extensions
# NOTE: PostGIS is a PostgreSQL database engine extension, NOT a PHP extension.
# PHP communicates with PostGIS via pdo_pgsql and standard spatial SQL/WKT/GeoJSON.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        gd \
        intl \
        zip \
        opcache \
        pcntl

# Install and enable Redis extension for high-throughput queues, cache, and distributed locks
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Production PHP & Opcache configurations
RUN { \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN { \
        echo 'upload_max_filesize=50M'; \
        echo 'post_max_size=50M'; \
        echo 'memory_limit=512M'; \
        echo 'max_execution_time=60'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/custom-php.ini

# Copy official Composer binary
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Configure dedicated non-root application user
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# Set working directory
WORKDIR /var/www/html

# Ensure proper stop signal for graceful worker shutdown (Queue & Reverb)
STOPSIGNAL SIGTERM

EXPOSE 9000

CMD ["php-fpm"]
