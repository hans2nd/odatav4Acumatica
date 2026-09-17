#!/bin/bash

# ==============================================================================
# Laravel Smooth Deployment Script (Zero-Downtime Approach)
# Usage: ./deploy.sh
# ==============================================================================

# Exit immediately if a command exits with a non-zero status
set -e

echo "🚀 Starting deployment process..."

# 1. Enter maintenance mode (allows existing requests to finish, shows 503 to new ones)
# We use || true so it doesn't stop if the app is already down
echo "🚧 Putting application into maintenance mode..."
php artisan down --render="errors::503" --secret="bypass-deploy" || true

# 2. Update codebase from Git (Optional: Uncomment if using Git)
# echo "📥 Pulling latest changes from Git..."
# git pull origin main

# 3. Install/Update Composer Dependencies (No Dev, Optimized)
echo "📦 Installing composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 4. Clear old caches
echo "🧹 Clearing old caches..."
php artisan optimize:clear
php artisan clear-compiled

# 5. Run Database Migrations (--force is required in production)
echo "🗄️ Running database migrations..."
php artisan migrate --force

# 6. Build caches for better performance
echo "⚡ Building configuration and route caches..."
php artisan optimize
php artisan view:cache
php artisan event:cache

# 7. Restart Queue Workers (Crucial for background jobs/scheduler)
echo "🔄 Restarting queue workers..."
php artisan queue:restart || true

# 8. Reload PHP-FPM (Optional: Uncomment and adjust for your PHP version if needed)
# echo "🌐 Reloading PHP-FPM..."
# sudo systemctl reload php8.2-fpm

# 9. Exit maintenance mode
echo "✅ Bringing application back online..."
php artisan up

echo "🎉 Deployment completed successfully!"
