# ================================
# Stage 1: Dependency build
# ================================
FROM composer:2.7 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ================================
# Stage 2: Runtime
# ================================
FROM php:8.2-cli-alpine

# Ensure SQLite + PDO SQLite are available (already included in base image)
RUN php -m | grep -q pdo_sqlite

# Create non-root user
RUN addgroup -g 1000 app \
 && adduser -D -G app -u 1000 app

WORKDIR /app

# Copy dependencies and source
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Fix permissions
RUN mkdir -p /app/data && chown -R app:app /app/data

USER app

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]