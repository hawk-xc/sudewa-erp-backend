composer install
composer dump-autoload

php artisan config:clear
php artisan route:clear
php artisan cache:clear

php artisan migrate:fresh --seed && php artisan db:seed DummyDataSeeder
php artisan db:seed MainCompanyWarehouseSeeder
php artisan db:seed PurchaseUnitTransactionDummySeeder