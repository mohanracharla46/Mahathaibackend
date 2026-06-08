#!/bin/sh

# Set the port dynamically from Render's PORT environment variable (fallback to 80)
PORT=${PORT:-80}
echo "Starting Apache on port $PORT..."

# Replace the port in Apache configuration files
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Wait for database if host is specified (useful during cold boots)
if [ -n "$DB_HOST" ]; then
  echo "Allowing database ($DB_HOST) 5 seconds to initialize..."
  sleep 5
fi

# Run caching commands for optimal production performance
echo "Caching configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations automatically in production if RUN_MIGRATIONS is true
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Start Apache in the foreground
exec apache2-foreground
