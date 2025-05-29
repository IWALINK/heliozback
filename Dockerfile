FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    libzip-dev \
    libpq-dev \
    libcurl4-openssl-dev \
    nano \
    netcat-openbsd

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies without running scripts (artisan doesn't exist yet)
RUN composer install --no-dev --no-scripts --optimize-autoloader

# Copy the rest of the application
COPY . .

# Copy .env.example to .env if .env doesn't exist
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Generate optimized autoloader and run Laravel scripts
RUN composer dump-autoload --optimize && \
    php artisan key:generate && \
    php artisan config:cache

# Create migration files (without running them)
RUN php artisan session:table --force 2>/dev/null || true && \
    php artisan queue:table --force 2>/dev/null || true

# Copy fixed entrypoint script
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www && \
    chmod -R 775 /var/www/storage && \
    chmod -R 775 /var/www/bootstrap/cache && \
    chgrp -R www-data /var/www/storage /var/www/bootstrap/cache

# Expose port 9000
EXPOSE 9000

# Use entrypoint script that waits for DB and runs migrations
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]