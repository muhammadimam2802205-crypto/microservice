FROM php:8.4-cli

# Install dependensi sistem
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP
RUN docker-php-ext-install pdo_mysql

# Copy composer dari image resmi
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy seluruh project
COPY . .

# Install dependensi PHP
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Expose port
EXPOSE 3003

# CMD dengan retry migration lalu serve
CMD bash -c ' \
    max_attempts=20; \
    attempt=0; \
    until php artisan migrate --force || [ $attempt -eq $max_attempts ]; do \
        attempt=$((attempt + 1)); \
        echo "Migration attempt $attempt/$max_attempts failed, retrying in 3 seconds..."; \
        sleep 3; \
    done && \
    php artisan serve --host=0.0.0.0 --port=3003'
