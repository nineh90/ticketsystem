#!/usr/bin/env bash
#
# Läuft bei jedem Containerstart, vor Apache.
#
# Hier steht ausschließlich Idempotentes. Insbesondere KEIN migrate:fresh und
# kein Seeder, der Daten ersetzt: das Skript läuft auch bei jedem Neustart des
# Containers, nicht nur beim Deploy.
set -euo pipefail

cd /var/www/html

echo '→ Warte auf die Datenbank …'
for i in $(seq 1 30); do
    if php -r '
        try {
            new PDO(
                sprintf("pgsql:host=%s;port=%s;dbname=%s",
                    getenv("DB_HOST"), getenv("DB_PORT") ?: 5432, getenv("DB_DATABASE")),
                getenv("DB_USERNAME"), getenv("DB_PASSWORD")
            );
            exit(0);
        } catch (Throwable $e) { exit(1); }
    ' 2>/dev/null; then
        break
    fi

    if [ "$i" = 30 ]; then
        echo '✗ Datenbank nicht erreichbar — Abbruch.' >&2
        exit 1
    fi

    sleep 2
done

# Diese beiden liefen wegen --no-scripts nicht beim Bauen nach. filament:upgrade
# legt die publizierten Filament-Assets unter public/ ab; ohne den Aufruf lädt
# die Oberfläche ohne Stile und ohne JavaScript.
echo '→ Pakete erkennen …'
php artisan package:discover --ansi
php artisan filament:upgrade

echo '→ Migrationen …'
php artisan migrate --force --no-interaction

# Nur die Ticket-Stadien, und die legt der Seeder über firstOrCreate an:
# vorhandene Stadien bleiben unangetastet, auch umbenannte.
echo '→ Grunddaten …'
php artisan db:seed --force --no-interaction

echo '→ Zwischenspeicher aufbauen …'
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Der Speicher-Link muss nach jedem Neubau des Abbilds neu gesetzt werden,
# weil public/ aus dem Abbild kommt.
php artisan storage:link --force || true

chown -R www-data:www-data storage bootstrap/cache

echo '✓ Bereit.'

exec "$@"
