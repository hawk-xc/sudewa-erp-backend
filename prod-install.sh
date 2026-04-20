#!/bin/bash

git config --global --add safe.directory /app

composer install
composer dump-autoload

php artisan config:clear
php artisan route:clear
php artisan cache:clear

php artisan key:generate --force
php artisan storage:link
# Run tests
php artisan test

# Confirmation for seeding
echo "Do you want to run the database installer/seeder? (yes/no): "
read -r response

case "$response" in
    [yY] | [yY][eE][sS])
        echo "Running installer/seeder..."
        php artisan migrate:fresh --seed
        ;;
    *)
        echo "Skipping seeding. Performing only standard migrations if any."
        php artisan migrate --force
        ;;
esac


php artisan config:cache
#php artisan route:cache
#php artisan optimize


