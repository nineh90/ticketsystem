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

## Zwei Einstiegsseiten statt eines Dashboards

Intern gibt es zwei Startseiten, und die Trennlinie ist eine Frage: kann ich
daran etwas tun?

| | Adresse | Was darauf steht |
|---|---|---|
| Mein Bereich | `/` | Meine Zahlen, meine Uhr, ungelesene Nachrichten, meine Tickets, wartende Kundenanliegen |
| Betrieb | `/betrieb` | Zahlen des Betriebs, alle laufenden Uhren, Geschehen, Verteilung |

Beide sind `Filament\Pages\Dashboard`-Ableitungen und sagen in `getWidgets()`
selbst, welche Karten sie tragen. Im `AdminPanelProvider` steht **keine**
gemeinsame Widget-Liste mehr — `discoverWidgets()` meldet sie nur noch als
Livewire-Komponenten an. Eine Karte, die nirgends in einem `getWidgets()`
steht, erscheint damit auch nirgends.

### Die Zahlen sind Wege

Jede Kachel, hinter der eine Ticketmenge steht, führt auf die Liste dieser
Tickets — Reiter und Filter schon gesetzt:

| Kachel | Ziel |
|---|---|
| Meine offenen Tickets | Reiter *Meine* |
| Fällig bis Sonntag | Reiter *Meine* + Zeitfenster *Fällig bis Sonntag* |
| Offen gesamt | Reiter *Offen* |
| Heute eingegangen | Reiter *Alle* + Zeitfenster *Heute eingegangen* |
| Liegt seit 3 Tagen | Reiter *Offen* + Zeitfenster *Ruhend* |

Die beiden Zeitkacheln bleiben absichtlich ohne Ziel: erfasste Zeiten haben
keine eigene Liste.

Zwei Dinge sind daran heikel, und beide sieht man nicht:

* **Die Parameter heißen `tab` und `filters`**, nicht `activeTab` und
  `tableFilters` (so stehen sie als `#[Url]` in Filaments `ListRecords`). Ein
  unbekannter Parameter wird stillschweigend verworfen — die Liste geht auf
  und steht auf ihrem Standardreiter. Genau so stand es im Kundenbereich
  monatelang falsch da.
* **Kachel und Liste müssen dieselbe Bedingung benutzen.** Deshalb stehen
  „überfällig", „ruhend" und „fällig bis" als Scopes am `Ticket` und nicht
  ausgeschrieben in Widget, Reiter und Filter. Wer auf eine 21 klickt, will
  21 Zeilen sehen; `KachelnFuehrenZurListeTest` nimmt jede verlinkte Kachel
  beim Wort und zählt nach.

Solche Adressen baut man deshalb **nicht von Hand**, sondern mit
`TicketResource::listeUrl()`. Dort steht auch, warum jede von ihnen ein leeres
Zeitfenster und ein `frisch=1` mit sich trägt — siehe den nächsten Abschnitt.

## Eingestelltes bleibt eingestellt

Die Ticketliste hält Filter, Suche und Sortierung über die Sitzung
(`persistFiltersInSession()` und Geschwister in `TicketsTable`), den aktiven
Reiter hält `ListTickets` selbst. Wer nach „Kunde: Landhaus, Reiter: Meine" ein
Ticket öffnet und über die Navigation zurückkommt, findet seinen Stand wieder.
Dasselbe gilt für die Vorauswahl im Kanban.

Die Sitzung, nicht die Adresse: ein Filter, der in der URL klebt, wandert in
Lesezeichen und weitergeschickte Links.

**Das stößt sich mit den Kacheln**, und die Auflösung ist der Grund für die
zwei Merkwürdigkeiten in `listeUrl()`:

| | greift auf die Sitzung zurück, wenn … | also braucht die Adresse … |
|---|---|---|
| Filter | die Adresse gar keinen mitbringt | ein Zeitfenster, notfalls leer |
| Suche | sie **leer** ist — nicht erst, wenn sie fehlt | `frisch=1`, das sie löscht |

Der Unterschied ist der ganze Punkt: bei der Suche reicht der leere Wert nicht.
Beide Fälle stehen als Test in `KachelnFuehrenZurListeTest`, samt der
Gegenprobe, dass ein normaler Aufruf über die Navigation nichts wegräumt.

## Kanban

Das Brett ist so hoch wie der Bildschirm und keinen Pixel höher; jede Spalte
scrollt für sich. Vorher wuchs es mit der längsten Spalte — bei
sechsundzwanzig Karten auf gut dreitausend Pixel, und die waagerechte
Bildlaufleiste saß ganz unten an deren Ende. Wer nach rechts wollte, musste
erst durch die ganze Spalte nach unten.

Abschließende Spalten zeigen höchstens `Kanban::KARTEN_JE_ABSCHLUSS_SPALTE`
Karten und darunter „… und N weitere". Gekappt wird nur die Anzeige: die Zahl
im Spaltenkopf zählt vollständig, und der Weg dahinter führt auf genau diese
Menge. Offene Spalten bleiben ungekürzt — jede Karte darin ist etwas, das
noch jemand anfassen muss.

## Nachrichten

Ein Chat neben den Tickets, an kein Ticket gebunden: `/nachrichten` innen,
`/kunde/nachrichten` außen. Für alles, wofür ein Ticket zu viel wäre — eine
Terminfrage, eine Rückfrage zur Rechnung, ein Hinweis an einen Kollegen.

Zwei Arten, und der Unterschied ist der Empfängerkreis:

* **Kundenunterhaltung** — gehört dem Kunden, nicht einer Person. Es gibt je
  Kunde genau einen Verlauf (`unique` auf `customer_id`). Es lesen alle
  Zugänge des Kunden und alle, die für ihn zuständig sind — dieselbe Regel wie
  bei Tickets und Zeiten (`Customer::sichtbarFuer`). Damit bleibt der Faden
  auch dann bedient, wenn jemand im Urlaub ist.
* **Interne Unterhaltung** — zwischen genau zwei von uns. Hier gilt als
  einzige Stelle im System **nicht** „Administrator sieht alles": wer nicht
  Teilnehmer ist, liest nicht mit. Siehe `UnterhaltungPolicy::view()`.

Der Lesestand steht je Beteiligtem als Zeitstempel
(`unterhaltung_teilnehmer.gelesen_bis`), nicht als Zähler — ein Zähler müsste
bei jeder Nachricht für jeden fortgeschrieben werden und liefe beim ersten
Fehlschlag still auseinander. Die Zahl an der Navigation kommt aus
`Unterhaltungen::ungelesen()` und ist bewusst **eine** Abfrage: sie läuft bei
jedem Seitenaufruf.

## Die Glocke

Jede Meldung trägt in ihren Daten eine **Herkunft** mit (`ticket:42`,
`unterhaltung:7`, `kunde:3` — siehe `App\Support\Herkunft`). Öffnet jemand
später genau diese Sache, gelten alle Meldungen dazu für ihn als gelesen:

| Geöffnet | Verstummt |
|---|---|
| Ticket (`ViewTicket`) bzw. Anliegen (`ViewAnliegen`) | `ticket:<id>` |
| Verlauf (`Unterhaltung::alsGelesenMarkieren`) | `unterhaltung:<id>` |
| Kundenakte (`EditCustomer`) | `kunde:<id>` |

Ohne das zählte die Glocke nur herunter, wenn man eine Meldung in ihr selbst
anklickt — wer die Antwort längst im Ticket gelesen hatte, trug die Zahl
trotzdem weiter vor sich her, und eine Zahl, die immer da ist, heißt nach der
dritten Woche nichts mehr.

Gelesenes **bleibt in der Liste stehen**, nur eben zurückgetreten (`opacity`
in `theme.css` auf `.fi-no-notification-read-ctn`); Neues behält Balken und
Farbgrund. Die Glocke ist auch ein kleines Gedächtnis dafür, wann etwas
hereinkam, und das kann eine Liste nicht sein, die sich beim Lesen leert.

Die Herkunft wird in `Benachrichtigung::zustellen()` untergemischt, weil
Filaments `Notification` nur ihre eigenen Felder kennt und alles andere
wegwirft. Beim Zurücklesen übergeht `Notification::fromArray()` den
zusätzlichen Schlüssel.

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
