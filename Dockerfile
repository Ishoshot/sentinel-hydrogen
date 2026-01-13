FROM dunglas/frankenphp:php8.4-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions including pcntl
RUN install-php-extensions \
    ctype \
    curl \
    dom \
    fileinfo \
    filter \
    hash \
    mbstring \
    openssl \
    pcre \
    pdo \
    pdo_pgsql \
    redis \
    session \
    tokenizer \
    xml \
    pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js (for asset building)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy dependency files first (for caching)
COPY composer.json composer.lock ./
COPY package.json package-lock.json ./

# Install dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --optimize-autoloader --no-scripts --no-interaction --no-dev
RUN npm ci && npm run build && npm prune --omit=dev

# Copy application code
COPY . .

# Run Laravel optimizations
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
