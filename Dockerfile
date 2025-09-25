# =============================================================================
# Base Stage - Common dependencies and extensions
# =============================================================================
FROM php:8.3-apache as base

# Install system dependencies and PHP extensions in one layer
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libssl-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    soap \
    zip \
    && docker-php-ext-enable soap \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Apache
RUN a2enmod rewrite
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
    </VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# =============================================================================
# Dependencies Stage - Install PHP dependencies with caching
# =============================================================================
FROM base as dependencies

# Copy composer files first for better layer caching
COPY --chown=www-data:www-data composer.* ./

# Install PHP dependencies (cached layer if composer files haven't changed)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    && composer clear-cache

# =============================================================================
# Development Stage - Include Xdebug and dev dependencies
# =============================================================================
FROM dependencies as development

# Install Xdebug for development
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Copy Xdebug configuration
COPY ./docker/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install dev dependencies
RUN composer install \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

# Copy application code
COPY --chown=www-data:www-data . .

# Generate optimized autoloader and run post-install scripts
RUN composer dump-autoload --optimize \
    && composer run-script post-autoload-dump

# Set permissions
RUN chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]

# =============================================================================
# Production Stage - Optimized for production deployment
# =============================================================================
FROM dependencies as production

# Copy application code (excluding dev files via .dockerignore)
COPY --chown=www-data:www-data . .

# Generate optimized autoloader and run scripts
RUN composer dump-autoload --optimize --classmap-authoritative \
    && composer run-script post-autoload-dump \
    && composer clear-cache

# Optimize Laravel for production
RUN php artisan config:cache || true \
    && php artisan route:cache || true \
    && php artisan view:cache || true

# Set production permissions
RUN chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Remove unnecessary files for production
RUN rm -rf \
    /var/www/html/tests \
    /var/www/html/.git* \
    /var/www/html/docker \
    /var/www/html/README.md

# Set Apache to run as www-data
USER www-data

EXPOSE 80
CMD ["apache2-foreground"]

# =============================================================================
# Queue Worker Stage - Specialized for queue processing
# =============================================================================
FROM development as queue-worker-dev

# Switch back to root for queue worker setup
USER root

# Queue workers don't need Apache, just PHP CLI
# Override the CMD to run queue worker instead
CMD ["php", "artisan", "queue:work", "database", "--queue=data-migration", "--sleep=3", "--tries=3", "--max-time=3600", "--timeout=300"]

FROM production as queue-worker

# Switch back to root for queue worker setup
USER root

# Queue workers don't need Apache, just PHP CLI
# Override the CMD to run queue worker instead
CMD ["php", "artisan", "queue:work", "database", "--queue=data-migration", "--sleep=3", "--tries=3", "--max-time=3600", "--timeout=300"]
