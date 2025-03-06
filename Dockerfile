# Use a specific tag to avoid metadata delays and ensure reproducibility
FROM php:8.1-fpm-bullseye

# Set working directory
WORKDIR /var/www

# Install dependencies in a single RUN to reduce layers and cache effectively
# Install dependencies including git, unzip, and zip extension
# Install dependencies including git, unzip, libzip-dev, and zip extension
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    curl \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy only necessary application files
COPY composer.json composer.lock /var/www/
COPY src/ /var/www/src/
COPY public/ /var/www/public/
COPY config/ /var/www/config/
COPY .env /var/www/

# For db
RUN mkdir /var/www/db
RUN sqlite3 /var/www/db/chat.db

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www

# Expose port
EXPOSE 8000

# Run PHP built-in server
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public/"]