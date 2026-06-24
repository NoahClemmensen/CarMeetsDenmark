###############################################################################
# Stage 1 – Build (install deps, compile assets, then throw this layer away)
###############################################################################
FROM php:8.4-alpine AS builder

ENV APP_ENV=prod

RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    zip \
    unzip

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

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# Ensure .env never boots in dev mode
RUN sed -i 's/APP_ENV=dev/APP_ENV=prod/' .env

RUN composer install --no-dev --optimize-autoloader

RUN php bin/console tailwind:build
RUN php bin/console asset-map:compile

###############################################################################
# Stage 2 – Runtime (only what is needed to serve the app)
###############################################################################
FROM php:8.4-alpine

ENV APP_ENV=prod

# Runtime libraries only (no -dev headers or compilers)
RUN apk add --no-cache \
    curl \
    libpng \
    oniguruma \
    libxml2 \
    libzip \
    icu-libs \
    freetype \
    libjpeg-turbo \
    bash

# Reuse compiled extensions from the builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/   /usr/local/etc/php/conf.d/

# Install Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.alpine.sh' | bash && \
    apk add --no-cache symfony-cli

WORKDIR /var/www

# Copy the built application (vendor is already --no-dev)
COPY --from=builder /var/www .

# Pre-warm the cache as www-data so no runtime writes are needed
RUN php bin/console cache:warmup

# Create the runtime-writable dirs so the named volumes mount onto existing,
# owned paths instead of being created lazily as root.
RUN mkdir -p var/sessions public/uploads

# Copy entrypoint before chown so everything gets correct ownership in one pass
COPY docker/setup.sh /setup.sh
RUN chmod +x /setup.sh

# Set correct ownership for the entire app including pre-warmed cache
RUN chown -R www-data:www-data /var/www /setup.sh

EXPOSE 8000
ENTRYPOINT ["/setup.sh"]
