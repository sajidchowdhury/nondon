FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    libsqlite3-dev \
    nano \
    dos2unix \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    mbstring \
    xml \
    bcmath \
    gd \
    zip \
    intl \
    pdo_sqlite \
    opcache \
    pcntl

# Install Redis extension (for future use)
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/nondon.ini

# Fix line endings in shell scripts (Windows CRLF to LF)
# Creates an entrypoint script that converts CRLF to LF in init.sh before running
RUN printf '#!/bin/bash\nif [ -f /var/www/html/docker/init.sh ]; then\n  dos2unix /var/www/html/docker/init.sh 2>/dev/null || sed -i "s/\\r$//" /var/www/html/docker/init.sh\nfi\nexec "$@"\n' > /usr/local/bin/fix-line-endings.sh \
    && chmod +x /usr/local/bin/fix-line-endings.sh

# Development: run as root to avoid volume permission issues on Windows
# In production, use a non-root user with proper volume ownership
ENTRYPOINT ["/usr/local/bin/fix-line-endings.sh"]
CMD ["php-fpm"]
