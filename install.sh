composer install

php artisan config:clear
php artisan route:clear
php artisan cache:clear

php artisan key:generate --force
php artisan storage:link
php artisan migrate --seed

php artisan config:cache
php artisan route:cache
php artisan optimize