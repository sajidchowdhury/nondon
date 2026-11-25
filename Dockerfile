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
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
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

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Switch to www-data user
USER www-data

# Default command
CMD ["php-fpm"]
