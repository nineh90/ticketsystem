#!/usr/bin/env bash
#
# Läuft AUF DEM VPS unter /docker/ticketsystem/deploy/deploy.sh.
#
# Wird von der GitHub-Action über SSH angestoßen. Der dafür hinterlegte
# Schlüssel ist in /root/.ssh/authorized_keys per erzwungenem Kommando genau
# auf dieses Skript festgenagelt — eine Shell bekommt man damit nicht.
set -euo pipefail

PROJEKT=/docker/ticketsystem
LOG=/var/log/ticketsystem-deploy.log

exec > >(tee -a "$LOG") 2>&1
echo "=== $(date '+%Y-%m-%d %H:%M:%S') Deploy gestartet ==="

cd "$PROJEKT"

# Stand holen. reset --hard statt pull: der Server ist kein Arbeitsplatz, hier
# soll nichts zusammengeführt werden, sondern exakt der Stand von origin/main
# liegen.
git fetch --quiet origin main
git reset --hard --quiet origin/main
echo "→ Stand: $(git rev-parse --short HEAD) — $(git log -1 --pretty=%s)"

cd "$PROJEKT/deploy"

# Bauen und tauschen. Migrationen und Zwischenspeicher erledigt das
# Einstiegsskript beim Start des neuen Containers.
docker compose up -d --build

# Alte, nun namenlose Abbilder wegräumen — sonst füllt sich die Platte binnen
# weniger Monate mit Zwischenständen.
docker image prune -f --filter 'label!=behalten' >/dev/null

echo "→ Warte auf den Container …"
for i in $(seq 1 30); do
    if [ "$(docker inspect -f '{{.State.Running}}' ticketsystem 2>/dev/null)" = 'true' ]; then
        break
    fi
    [ "$i" = 30 ] && { echo '✗ Container läuft nicht.' >&2; exit 1; }
    sleep 2
done

# Der Planer hält die Uhr (Erinnerungen an Treffen). Er ist nicht kritisch für
# die Auslieferung, deshalb bricht der Deploy hier nicht ab — aber er wird
# genannt: ein Scheduler, der stillsteht, fällt sonst monatelang niemandem auf.
# Genau so steht der von kein-einzelfall seit Wochen als unhealthy da.
if [ "$(docker inspect -f '{{.State.Running}}' ticketsystem-planer 2>/dev/null)" = 'true' ]; then
    echo "→ Planer läuft."
else
    echo "! Der Planer läuft NICHT — Erinnerungen an Treffen gehen nicht raus." >&2
    docker logs --tail 20 ticketsystem-planer 2>&1 | sed 's/^/    /' >&2 || true
fi

# Gegenprobe von innen: Traefik meldet auch dann noch 200, wenn die Anwendung
# selbst gerade eine Fehlerseite ausliefert.
if docker exec ticketsystem php artisan about --only=environment >/dev/null 2>&1; then
    echo "✓ Deploy fertig."
else
    echo '✗ Die Anwendung antwortet nicht — Protokoll:' >&2
    docker logs --tail 40 ticketsystem >&2
    exit 1
fi
