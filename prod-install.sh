git config --global --add safe.directory /app

#composer install

composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

php artisan config:clear
php artisan route:clear
php artisan cache:clear

php artisan key:generate --force
php artisan storage:link
php artisan migrate:fresh --seed

php artisan config:cache
#php artisan route:cache
#php artisan optimize
