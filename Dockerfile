FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev libonig-dev libxml2-dev \
    nginx supervisor \
 && docker-php-ext-install pdo_mysql mbstring zip \
 && rm -rf /var/lib/apt/lists/*

# Make PHP-FPM listen on all interfaces for TCP port 9000
RUN sed -i 's|listen = .*|listen = 0.0.0.0:9000|' /usr/local/etc/php-fpm.d/www.conf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . .
COPY aiven-ca.pem /var/www/html/aiven-ca.pem

# Install PHP dependencies
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# --- CRITICAL: Create Laravel required cache directories ---
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Copy nginx and supervisor configs
COPY ./docker/nginx.conf /etc/nginx/sites-available/default
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose ports
EXPOSE 80 9000

# Start supervisord (nginx + php-fpm)
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
