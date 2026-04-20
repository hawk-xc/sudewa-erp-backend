#!/bin/bash

# Script khusus untuk update otomatis (CI/CD)
# Dibuat untuk dijalankan tanpa interaksi user

# Keluar dari script jika ada error
set -e

echo "--- UPDATE STARTED ---"

# 1. Update Dependencies
echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

# 2. Clear All Caches
echo "Clearing application cache..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# 3. Database Migration
# --force penting agar script tidak berhenti menunggu konfirmasi di production
echo "Running database migrations..."
php artisan migrate --force

# 4. Production Optimization
# Membuat cache baru untuk performa maksimal
echo "Optimizing configuration and routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Storage Link (Ensure link exists)
# php artisan storage:link --force || true

# 6. Restart Docker / Services (Opsional)
# Jika Anda menggunakan Docker Compose di server, jalankan perintah ini:
# echo "Restarting Docker containers..."
docker-compose restart

echo "--- UPDATE FINISHED SUCCESSFULLY ---"
