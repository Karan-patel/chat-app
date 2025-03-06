# Use a specific tag to avoid metadata delays and ensure reproducibility
FROM php:8.1-fpm-bullseye

# Set working directory
WORKDIR /var/www

# Install dependencies in a single RUN to reduce layers and cache effectively
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy application files (assumes project root structure with src/, public/, etc.)
COPY . /var/www

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/db

# Expose port
EXPOSE 8000

# Run PHP built-in server
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public/"]