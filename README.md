# Ticketsystem Nils-Digital

Internes Ticket- und Projektsystem. Struktur: **Kunde → Projekt → Ticket**.
Läuft unter `intern.nils-digital.de` auf dem Hostinger-VPS.

**Stack:** Laravel 13 · Filament 5 · PostgreSQL 16 · Tailwind 4 · PHP 8.4

## Lokal starten

```bash
bin/start
```

Das Skript prüft die Voraussetzungen, fährt den Postgres-Container hoch,
installiert Abhängigkeiten, migriert, baut die Assets und startet den Server
auf <http://localhost:8000>.

### Einmalige Voraussetzungen

```bash
sudo dnf install composer php-pgsql php-intl php-gd php-pecl-zip

# Datenbank-Container (Port 5433, weil 5432 vom Landhaus-Projekt belegt ist)
podman run -d --name ticketsystem-postgres \
  -e POSTGRES_USER=ticketsystem -e POSTGRES_PASSWORD=ticketsystem_dev \
  -e POSTGRES_DB=ticketsystem -p 5433:5432 \
  -v ticketsystem-pgdata:/var/lib/postgresql/data \
  --restart unless-stopped docker.io/library/postgres:16

# Test-Datenbank
podman exec ticketsystem-postgres psql -U ticketsystem -d ticketsystem \
  -c "CREATE DATABASE ticketsystem_test OWNER ticketsystem;"
```

## Tests

```bash
php artisan test
```

Die Tests laufen bewusst gegen **Postgres** (`ticketsystem_test`), nicht gegen
das Laravel-übliche SQLite-in-memory: produktiv läuft Postgres, und Dinge wie
`SELECT … FOR UPDATE` bei der Ticketnummern-Vergabe verhalten sich unter SQLite
anders oder gar nicht.

## Zugang

Ein Konto allein reicht nicht. `User::canAccessPanel()` verlangt zusätzlich
`panel_zugang = true` und `aktiv = true`; die Rolle `kunde` kommt ins interne
Panel grundsätzlich nicht hinein (sie bekommt später ein eigenes).

Nutzer freischalten:

```bash
php artisan tinker --execute='
  App\Models\User::where("email","…")->update([
      "rolle" => "admin", "panel_zugang" => true,
  ]);'
```

## Zwei Dinge, die beim Ändern leicht kaputtgehen

**Sicherheits-Header.** `SicherheitsHeader` steht in `bootstrap/app.php` *und*
in der Middleware-Liste des `AdminPanelProvider`. Beides ist nötig: Filament
baut seinen Stack selbst und durchläuft die `web`-Gruppe nicht. Fliegt der
Eintrag im Panel raus, liefert die Oberfläche stillschweigend keine Header mehr
aus — `PanelZugangTest` schlägt dann an.

**CSP und Alpine.** Die Policy erlaubt `unsafe-eval`, weil Livewire und Alpine
sonst wortlos aufhören zu arbeiten (Knöpfe reagieren einfach nicht). Das ist
eine bewusste Abweichung von `kein-einzelfall`, das ohne JS-Framework gebaut
ist und die Policy deshalb enger fassen kann. Details im Klassenkommentar.

## Dokumentation

Der vollständige Aufbauplan liegt unter
`~/.claude/plans/ich-m-chte-gerne-ein-joyful-badger.md`.
