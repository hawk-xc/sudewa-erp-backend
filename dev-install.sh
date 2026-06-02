#!/bin/bash

composer install
composer dump-autoload

php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Run tests first
php artisan test

# Confirmation for seeding
echo "Do you want to run the database installer/seeder? (yes/no): "
read -r response

case "$response" in
    [yY] | [yY][eE][sS])
        echo "Running installer/seeder..."
        php artisan migrate:fresh --seed
        php artisan db:seed PersonSeeder
        php artisan db:seed DummyDataSeeder
        php artisan db:seed MainCompanyWarehouseSeeder
        # php artisan db:seed PurchaseUnitTransactionDummySeeder
        ;;
    *)
        echo "Skipping seeding."
        ;;
esac