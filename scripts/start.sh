#!/bin/sh
set -e

# Set the port dynamically from Render's PORT environment variable (fallback to 80)
PORT=${PORT:-80}
echo "Starting on port $PORT..."

# Replace the port in Apache configuration files
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Ensure storage and bootstrap directories have correct permissions at runtime
echo "Setting runtime permissions for storage and bootstrap/cache..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# If APP_KEY is injected as an env variable by Render, write it to .env
# This ensures Laravel picks it up when config is cached
if [ -n "$APP_KEY" ]; then
    echo "APP_KEY found in environment, injecting into .env..."
    sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" /var/www/html/.env
fi

# Generate APP_KEY if still missing
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    echo "Generating new encryption key..."
    php artisan key:generate --force
fi

# Create SQLite database file if connection is SQLite and database is missing
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    DB_DATABASE_FILE="/var/www/html/database/database.sqlite"
    if [ ! -f "$DB_DATABASE_FILE" ]; then
        echo "Creating SQLite database file at $DB_DATABASE_FILE..."
        mkdir -p /var/www/html/database
        touch "$DB_DATABASE_FILE"
        chown www-data:www-data "$DB_DATABASE_FILE"
        chmod 664 "$DB_DATABASE_FILE"
    fi
fi

# Wait for PostgreSQL database to be ready if host is specified
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "pgsql" ]; then
    echo "Waiting for PostgreSQL at $DB_HOST:${DB_PORT:-5432} to be ready..."
    MAX_RETRIES=30
    COUNT=0
    until php -r "
        \$conn = @pg_connect('host=${DB_HOST} port=${DB_PORT:-5432} dbname=${DB_DATABASE} user=${DB_USERNAME} password=${DB_PASSWORD}');
        if (\$conn) { echo 'ok'; pg_close(\$conn); exit(0); }
        exit(1);
    " 2>/dev/null || [ $COUNT -eq $MAX_RETRIES ]; do
        COUNT=$((COUNT + 1))
        echo "Waiting for DB... attempt $COUNT/$MAX_RETRIES"
        sleep 2
    done
    echo "Database is ready!"
fi

# Clear cached config before caching again (avoids stale cache issues)
echo "Clearing old cached config..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cache configuration, routes, and views for production performance
echo "Caching configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations automatically in production if RUN_MIGRATIONS is true
if [ "$RUN_MIGRATIONS" = "true" ] || [ -z "$RUN_MIGRATIONS" ]; then
    echo "Running database migrations..."
    php artisan migrate --force

    echo "Seeding database with default menu data..."
    php artisan db:seed --force
fi

# Run queued Uber dispatch jobs in the same service container.
if [ "$QUEUE_CONNECTION" != "sync" ]; then
    echo "Starting Laravel queue worker..."
    su -s /bin/sh www-data -c "php artisan queue:work --sleep=3 --tries=4 --timeout=60 --max-time=3600" &
fi

# Start Apache in the foreground
echo "Starting Apache web server..."
exec apache2-foreground
