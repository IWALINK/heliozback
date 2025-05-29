#!/bin/bash

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
while ! nc -z mysql 3306; do
  sleep 1
done
echo "MySQL is ready!"

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Create session table if it doesn't exist
php artisan session:table --force 2>/dev/null || true
php artisan migrate --force

# Cache configuration
php artisan config:cache
php artisan route:cache

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec php-fpm