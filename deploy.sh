echo "🧹 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "🛠 Caching config and routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "📦 Updating Composer packages..."
composer update

echo "🏗 Building frontend assets..."
npm run build

echo "✅ Deployment complete!"
