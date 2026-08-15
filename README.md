# Ticketsystem Nils-Digital

Internes Ticket- und Projektsystem. Struktur: **Kunde → Projekt → Ticket**.

Live: <https://intern.nils-digital.de> — Push auf `main` rollt aus.

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

## Die zwei Bereiche

| | Adresse | Wer |
|---|---|---|
| Intern | `/` | Administratoren und Mitarbeiter |
| Kundenbereich | `/kunde` | Rolle `kunde`, je einem Kunden zugeordnet |

Getrennte Guards (`web` und `kunde`, siehe `config/auth.php`): man kann in
beiden **gleichzeitig** angemeldet sein. Ohne das müsste man sich zum Ansehen
des Kundenbereichs jedes Mal intern abmelden.

Was der Kundenbereich zeigt, steht ausschließlich unter `app/Filament/Kunde`.
Alles andere existiert dort nicht — die Umkehrung zum internen Panel, wo jede
Ressource einzeln wissen müsste, dass gerade ein Kunde zusieht.

Kundenzugang anlegen: *Kunden → der Kunde → Zugänge*. Rolle und Zuordnung
werden dabei gesetzt, ein Startpasswort wird vorgeschlagen. Wer ein Passwort
zugeteilt bekommt, muss es beim nächsten Anmelden wechseln — die Regel steht
in `User::booted()`, die Umleitung macht `PasswortWechseln`.

Der Kunde ist die **Akte**: Stammdaten, Vertrag, Hoster und Demo-Adresse,
mehrere Ansprechpartner (`kontakte`), ein verschlüsselter Zugangsdaten-Tresor
(`zugangsdaten`) und je Projekt Phase, zwei Adressen und Meilensteine. Was
davon beim Kunden ankommt, entscheiden Schalter je Datensatz — Vorgabe beim
Tresor ist *nicht sichtbar*. Ausführlich in
[`docs/betrieb.md`](docs/betrieb.md#die-kundenakte).

## Zugang

Ein Konto allein reicht nicht. `User::canAccessPanel()` verlangt zusätzlich
`panel_zugang = true` und `aktiv = true`, und jede Rolle gehört in genau ein
Panel: `kunde` ausschließlich in den Kundenbereich, alle anderen ausschließlich
ins interne.

Nutzer freischalten:

```bash
php artisan tinker --execute='
  App\Models\User::where("email","…")->update([
      "rolle" => "admin", "panel_zugang" => true,
  ]);'
```

## Drei Dinge, die beim Ändern leicht kaputtgehen

**Sicherheits-Header.** `SicherheitsHeader` steht in `bootstrap/app.php` *und*
in der Middleware-Liste des `AdminPanelProvider`. Beides ist nötig: Filament
baut seinen Stack selbst und durchläuft die `web`-Gruppe nicht. Fliegt der
Eintrag im Panel raus, liefert die Oberfläche stillschweigend keine Header mehr
aus — `PanelZugangTest` schlägt dann an.

**Benachrichtigungen und die Warteschlange.** `Benachrichtigung::zustellen()`
verschickt über `notifyNow()`, umgeht die Warteschlange also bewusst. Filaments
`DatabaseNotification` ist ein `ShouldQueue`; bei `QUEUE_CONNECTION=database`
und ohne Worker — und einen Worker gibt es hier nicht — landen die Meldungen
sonst in der `jobs`-Tabelle und kommen nie an. In den Tests fällt das nicht
auf, weil `phpunit.xml` die Warteschlange auf `sync` stellt; deshalb prüft
`KundenbereichTest::test_benachrichtigung_kommt_auch_ohne_worker_an()` genau
diesen Fall mit `database`.

**CSP und Alpine.** Die Policy erlaubt `unsafe-eval`, weil Livewire und Alpine
sonst wortlos aufhören zu arbeiten (Knöpfe reagieren einfach nicht). Das ist
eine bewusste Abweichung von `kein-einzelfall`, das ohne JS-Framework gebaut
ist und die Policy deshalb enger fassen kann. Details im Klassenkommentar.

## Dokumentation

- [`docs/betrieb.md`](docs/betrieb.md) — Live-System, Deploy, Backup, Nutzer
- [`docs/n8n.md`](docs/n8n.md) — Schnittstelle für automatisch erzeugte Tickets

Der vollständige Aufbauplan liegt unter
`~/.claude/plans/ich-m-chte-gerne-ein-joyful-badger.md`.
