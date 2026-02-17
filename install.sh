composer install
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
php artisan config:cache
php artisan route:cache
php artisan optimize
