#!/usr/bin/env bash
#
# Tägliches Backup der Ticketsystem-Datenbank.
#
# Auf dem VPS nach /usr/local/bin/ticketsystem-backup legen und per Cron
# aufrufen:
#   30 3 * * * /usr/local/bin/ticketsystem-backup
#
# Bis hierhin gab es auf dieser Maschine keine dokumentierte Backup-Routine —
# auch nicht für kein-einzelfall oder n8n.
set -euo pipefail

ZIEL=/var/backups/ticketsystem
BEHALTEN=14
STAND=$(date '+%Y-%m-%d-%H%M')

mkdir -p "$ZIEL"

# pg_dump im Container des n8n-Stacks. Der Abzug wird sofort komprimiert,
# unkomprimiert wäre er bei ein paar tausend Tickets schnell dreistellig in MB.
if ! docker exec n8n-postgres-1 \
        pg_dump -U ticketsystem -d ticketsystem --clean --if-exists \
        | gzip -9 > "$ZIEL/ticketsystem-$STAND.sql.gz"; then
    echo "Backup fehlgeschlagen: $STAND" >&2
    # Angefangene Datei nicht liegen lassen — sie sähe sonst aus wie ein
    # gültiges Backup und man merkte den Ausfall erst beim Wiederherstellen.
    rm -f "$ZIEL/ticketsystem-$STAND.sql.gz"
    exit 1
fi

# Eine gzip-Datei, die sich nicht entpacken lässt, ist kein Backup. Lieber
# jetzt merken als im Ernstfall.
if ! gzip -t "$ZIEL/ticketsystem-$STAND.sql.gz"; then
    echo "Backup ist beschädigt: $STAND" >&2
    rm -f "$ZIEL/ticketsystem-$STAND.sql.gz"
    exit 1
fi

# Ältere Stände wegräumen, die neuesten behalten.
find "$ZIEL" -name 'ticketsystem-*.sql.gz' -type f \
    | sort -r \
    | tail -n "+$((BEHALTEN + 1))" \
    | xargs -r rm -f

echo "Backup: $ZIEL/ticketsystem-$STAND.sql.gz ($(du -h "$ZIEL/ticketsystem-$STAND.sql.gz" | cut -f1))"
