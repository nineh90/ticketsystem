# Ticketsystem — Laufzeitabbild
#
# Mehrstufig: die PHP-Abhängigkeiten entstehen in einem Composer-Abbild, die
# Assets in einem Node-Abbild, und im Ergebnis landet beides zusammen mit dem
# Code in einem schlanken PHP-Apache. Weder Node noch Composer bleiben im
# ausgelieferten Abbild zurück.
#
# Gebaut wird auf dem VPS (deploy/deploy.sh), so wie bei kein-einzelfall.

# --- Stufe 1: PHP-Abhängigkeiten -------------------------------------------
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts: die Skripte rufen artisan auf, und artisan braucht eine
# vollständige Anwendung samt .env — beides gibt es zur Bauzeit nicht.
# package:discover und filament:upgrade laufen deshalb im Einstiegsskript.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# --- Stufe 2: Frontend ------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /build

# Erst die Manifeste kopieren: solange sich package-lock.json nicht ändert,
# bleibt der npm-Schritt im Docker-Cache und der Deploy dauert Sekunden statt
# Minuten.
COPY package.json package-lock.json ./
RUN npm ci

# Das Theme zieht Filaments CSS aus vendor/, deshalb muss vendor beim
# Asset-Build vorliegen.
COPY --from=composer-deps /app/vendor ./vendor
COPY resources ./resources
COPY app ./app
COPY vite.config.js ./

RUN npm run build

# --- Stufe 3: Laufzeit ------------------------------------------------------
FROM php:8.4-apache

# pdo_pgsql für die Datenbank, intl für Datums- und Zahlenformate (Filament
# besteht darauf), zip und gd für Anhänge und Bildverarbeitung.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpq-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        intl \
        zip \
        gd \
        opcache \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# Der Dokumentenstamm zeigt auf public/. Damit liegen app/, storage/ und die
# .env außerhalb dessen, was Apache je ausliefern könnte — dasselbe Vorgehen
# wie im Dockerfile von fahrlehrerin_sarah.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
    && a2enmod rewrite headers expires

COPY deploy/php.ini /usr/local/etc/php/conf.d/ticketsystem.ini

WORKDIR /var/www/html

COPY . .
COPY --from=composer-deps /app/vendor ./vendor
COPY --from=assets /build/public/build ./public/build

# storage und bootstrap/cache müssen dem Apache-Benutzer gehören, sonst kann
# Laravel weder Logs noch Ansichten schreiben.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY deploy/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
