#!/bin/bash
# Make sure this file has executable permissions, run `chmod +x run-app.sh`

# Build assets using NPM
npm run build

# Clear cache
php artisan optimize:clear

# Cache the various components of the Laravel application
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# Run any database migrations and force it
php artisan migrate --force
php artisan config:clear

#php artisan migrate:fresh --force



# NIXPACKS_BUILD_CMD="php artisan optimize:clear && php artisan optimize && php artisan cache:clear && php artisan config:clear && php artisan migrate --force"
