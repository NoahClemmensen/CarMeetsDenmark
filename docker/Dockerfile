FROM php:8.4

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install Composer (only once)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash && \
    apt-get install -y symfony-cli

WORKDIR /var/www
COPY . .

RUN composer install

RUN php bin/console tailwind:build
RUN php bin/console asset-map:compile

# Pre-warm the cache as www-data so no runtime writes are needed
RUN APP_ENV=prod php bin/console cache:warmup

# Copy entrypoint before chown so everything gets correct ownership in one pass
COPY docker/setup.sh /setup.sh
RUN chmod +x /setup.sh

# Set correct ownership for the entire app including pre-warmed cache
RUN chown -R www-data:www-data /var/www /setup.sh

EXPOSE 8000
ENTRYPOINT ["/setup.sh"]
