FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_pgsql gd zip bcmath opcache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Update Apache configuration to point to Laravel's public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure custom PHP settings (increase upload limits, optimize memory)
RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/docker-php-custom.ini \
    && echo "upload_max_filesize=50M" >> /usr/local/etc/php/conf.d/docker-php-custom.ini \
    && echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/docker-php-custom.ini \
    && echo "max_execution_time=60" >> /usr/local/etc/php/conf.d/docker-php-custom.ini

# Set working directory
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . .

# Copy environment file (use production config if available, otherwise fallback to example)
RUN cp .env.production .env || cp .env.example .env

# Install dependencies (ignoring dev dependencies for production optimization)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create necessary directories and set permissions
RUN mkdir -p storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy the startup entrypoint script
COPY scripts/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Expose port 80 (Render maps its PORT to this)
EXPOSE 80

# Use the entrypoint script to run migrations and start Apache
CMD ["/usr/local/bin/start.sh"]
