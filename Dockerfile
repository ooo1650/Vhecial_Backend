FROM php:8.2-apache

# ── System dependencies ───────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    git \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql zip gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ── Apache config ─────────────────────────────────────────────────────────
RUN a2enmod rewrite headers

COPY apache.conf /etc/apache2/sites-available/000-default.conf

# ── Install Composer ──────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Copy application code ─────────────────────────────────────────────────
COPY . /var/www/html/

WORKDIR /var/www/html

# ── Install PHP dependencies (PHPMailer) ──────────────────────────────────
# composer.json lives one level up; copy it in so we can install here
COPY ../composer.json ./composer.json
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ── Uploads directory permissions ─────────────────────────────────────────
RUN mkdir -p uploads/avatars uploads/vehicles \
    && chown -R www-data:www-data uploads \
    && chmod -R 775 uploads

EXPOSE 80
