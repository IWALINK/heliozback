#!/bin/bash

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
while ! nc -z mysql 3306; do
  sleep 1
  echo "Still waiting for MySQL..."
done
echo "MySQL is ready!"

# Wait a bit more to ensure MySQL is fully initialized
sleep 5

# Test database connection
echo "Testing database connection..."
until php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected';" 2>/dev/null; do
  echo "Database not ready, waiting..."
  sleep 2
done
echo "Database connection successful!"

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Create session table
echo "Creating session table..."
php artisan session:table 2>/dev/null || echo "Session table command completed"

# Run migrations again to ensure all tables are created
php artisan migrate --force

# Cache configuration for better performance
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache 2>/dev/null || echo "No routes to cache"

# Fix permissions after any file operations
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec php-fpm