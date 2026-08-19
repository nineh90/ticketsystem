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
(`zugangsdaten`), **Dokumente** (`dokumente`) und je Projekt Phase, zwei
Adressen und Meilensteine. Was davon beim Kunden ankommt, entscheiden Schalter
je Datensatz — Vorgabe bei Tresor und Dokumenten ist *nicht sichtbar*.
Ausführlich in [`docs/betrieb.md`](docs/betrieb.md#die-kundenakte).

Die Akte hat eine eigene **Ansichtsseite** (`/customers/{id}`), auf der man
landet; das Formular liegt einen Knopf weiter. Oben stehen vier Zahlen —
offene Tickets, erledigte, erfasste Zeit, offene Posten — darunter zwei
Verläufe über zwölf Monate: gebuchte Zeit je Monat und Tickets je Monat
(eingegangen gegen erledigt). Die beiden Reihen des zweiten Diagramms sind
absichtlich nicht gleich lang: gezählt wird nach Datum des Ereignisses, ein
Ticket vom März steht bei Erledigung im Mai in beiden Monaten.

## Angebote, Rechnungen, Verträge

Die PDF entsteht in **sevDesk** und wird hier nur abgelegt (`dokumente`,
Platte `local` wie die Ticket-Anhänge, also außerhalb von `public/`). Die
Felder daneben sind bewusst wenige: Art, Titel, Nummer, Datum, Frist, Betrag,
Stand. Positionen und Steuersätze stehen im PDF und bleiben dort — eine zweite
Wahrheit daneben wäre die, die als Erste veraltet.

Welche Stände es gibt, entscheidet die Art (`DokumentArt::staende()`): ein
Angebot wird angenommen oder abgelehnt, eine Rechnung bezahlt, ein Vertrag hat
gar keinen. Die Spalte `faellig_am` trägt zwei Bedeutungen — beim Angebot
"gültig bis", bei der Rechnung "zahlbar bis"; die Beschriftung richtet sich
nach der Art.

**Der Kunde sieht den Bereich erst, wenn etwas darin steht.** `canAccess()`
prüft, ob es für ihn ein freigegebenes Dokument gibt, und steuert damit
Menüpunkt und Direktaufruf zugleich. Ein Menüpunkt, der ein Jahr lang leer
ist, sieht nicht nach "kommt noch" aus — man gewöhnt sich an, ihn zu
übergehen, und übersieht ihn dann auch, wenn das erste Angebot darin liegt.

Ein offenes Angebot kann der Kunde **annehmen oder ablehnen**. Seine Antwort
landet mit Zeitstempel und Person am Dokument (`beantwortet_at`,
`beantwortet_von`) — daran ist hinterher zu erkennen, ob er entschieden hat
oder ob wir den Stand eingetragen haben. Wir erfahren es doppelt: über die
Glocke und im Ereignisstrom unter *Betrieb*, wo "Angebote" ein eigener Filter
ist. Es ist der einzige Ereignistyp ohne Ticket; er trägt deshalb den Kunden
als Bezug (`Ereignis::$kontext`).

## Abrechnung vorbereiten

`/abrechnung`. Beantwortet die Frage, die vor jeder Rechnung stand und
Handarbeit war: **bei wem ist abrechenbare Zeit aufgelaufen, die noch auf
keiner Rechnung steht.** Der Schalter `abrechenbar` hing seit dem ersten Tag
an jeder Zeitbuchung und wurde von nichts ausgewertet.

Der Weg im Alltag: Rechnung in sevDesk schreiben → PDF in der Kundenakte unter
*Dokumente* hochladen → am Dokument **Zeiten zuordnen**. Danach sind die
Buchungen als abgerechnet markiert (`time_entries.dokument_id`) und fallen aus
der Liste.

Ein Verweis auf das Dokument und **kein Stichdatum** am Kunden. Ein Datum
beantwortet „was ist offen" auch, aber nicht die zweite Frage: *welche*
Stunden stecken in dieser Rechnung. Und eine nachgetragene Buchung aus dem
Vormonat fiele bei einem Stichdatum stillschweigend unter den Tisch.

Was **nicht** mitzählt, steht in `TimeEntry::scopeOffenZumAbrechnen()`: nicht
abrechenbare Buchungen, laufende Uhren (deren `minuten` steht noch auf 0) und
Buchungen über null Minuten. Wer welche sieht, entscheidet wie überall
`Ticket::sichtbarFuer` — ein Mitarbeiter sieht die offene Zeit seiner Kunden.
Soll das strenger sein, genügt ein `istAdmin()` in `Support\Abrechnung`; Seite
und Aktion gehen beide durch diese Klasse.

Stundensätze gibt es im System nicht und sollen es nicht geben — deshalb
Stunden und keine Beträge. Die Rechnung entsteht in sevDesk.

**Der Kunde sieht davon die Summe**, nicht die Posten: an einem Dokument steht
„Enthaltene Arbeitszeit: 5:25 h", sobald etwas zugeordnet ist. Die
Tätigkeitstexte der Buchungen bleiben draußen — die sind für interne Augen
geschrieben. An die Buchungen selbst kommt ein Kundenzugang unverändert nicht
heran (`TimeEntry::sichtbarFuer` gibt ihm `1 = 0`).

## Zwei Einstiegsseiten statt eines Dashboards

Intern gibt es zwei Startseiten, und die Trennlinie ist eine Frage: kann ich
daran etwas tun?

| | Adresse | Was darauf steht |
|---|---|---|
| Mein Bereich | `/` | Meine Zahlen, meine Uhr, ungelesene Nachrichten, meine Tickets, wartende Kundenanliegen |
| Betrieb | `/betrieb` | Zahlen des Betriebs, alle laufenden Uhren, Geschehen, offene Tickets je Kunde, erfasste Zeit je Kunde |

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

## Die Adresse eines Tickets

`/tickets/dlh-3-allergene-pflegen` statt `/tickets/7`. Die Reihenfolge ist die
ganze Konstruktion: **die Kennung steht vorn und löst allein auf, der Titel
dahinter ist Beiwerk.** Beides steckt in `Ticket::getRouteKey()` und
`resolveRouteBindingQuery()` — keine Slug-Spalte, keine Migration; Kürzel und
Nummer sind in der Datenbank schon je für sich eindeutig.

Daraus folgt, was sonst weh täte:

* **Titel dürfen sich ändern.** Ein verschickter Link bleibt gültig, weil der
  Titelteil beim Auflösen weggeworfen wird.
* **Titel dürfen doppelt sein.** „Impressum anpassen" liegt bei zwei Kunden —
  `kev-12-…` und `sar-15-…` sind trotzdem verschiedene Adressen.
* **Die alte Form `/tickets/7` funktioniert weiter.** Nicht aus Höflichkeit:
  in den gespeicherten Benachrichtigungen stehen fertige Adressen mit der ID
  darin, und deren „Ansehen"-Knopf soll auch in einem halben Jahr noch etwas
  öffnen.

`Str::slug()` wird mit `'de'` aufgerufen, sonst fallen die Umlaute weg statt
umschrieben zu werden — aus „Grüße" würde „grusse". Die n8n-Schnittstelle gibt
dieselbe Adresse zurück (`docs/n8n.md`), weil sie von dort in Mails wandert.

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

## Meldungen per Mail

Jede Meldung, die an der Glocke landet, kann zusätzlich als Mail hinausgehen.
Der Schalter sitzt **je Zugang** (*Verwaltung → Nutzer → E-Mail bei
Meldungen*), Vorgabe aus — der Versand wird stufenweise eingeführt: erst ein
Zugang, dann Kevin, viel später ein Kunde.

Angeschlossen ist er an `Benachrichtigung::zustellen()`, also an derselben
einen Stelle, durch die jeder Empfängerkreis läuft. Eine zweite Stelle, die
Mails verschickt, müsste die Regel „wer darf was erfahren" ein zweites Mal
kennen.

Der Schalter allein sagt nur *ob*. **Worüber**, steht daneben als Auswahl
(`users.mail_ereignisse`, die Fälle in `MailEreignis`): neues Anliegen,
Antwort eines Kunden, neue Nachricht, geänderte Stammdaten, Antwort auf ein
Angebot — dazu zwei, die nach außen gehen und heute ohne Wirkung sind. Ohne
diese Auswahl wäre der Versand alles oder nichts, und wer täglich fünf Mails
bekommt, von denen ihn zwei angehen, übergeht nach einer Woche alle fünf.

**Leer ist nicht dasselbe wie nicht gesetzt.** Ist nichts gespeichert (`null`),
kommt alles durch — auch Ereignisse, die es beim Anlegen des Zugangs noch
nicht gab. Eine beim Einführen festgeschriebene Liste schlösse jeden künftigen
Typ stillschweigend aus, und das fiele niemandem auf. Eine leere Liste
entsteht dagegen nur, wenn jemand bewusst alle Haken entfernt hat, und heißt
dann wirklich nichts.

Die Spalte ist **jsonb, nicht json**: für `json` kennt Postgres keinen
Gleichheitsoperator, und jedes `select distinct users.*` im System bricht ab,
sobald eine json-Spalte an der Tabelle hängt — betroffen wären sämtliche
Mitarbeiter-Auswahlen.

**Kundenzugänge bekommen nie eine Mail**, gleich was am Schalter steht
(`User::bekommtMailMeldungen`). Ihre Adressen hat niemand bestätigt — sie
stammen daher, dass wir sie beim Anlegen eingetippt haben. Ein versehentlich
gesetzter Haken wäre sonst der Weg, auf dem ein Tickettitel an eine geratene
oder geteilte Adresse geht. Die Zeile fällt, wenn der Versand nach außen
drankommt, dann aber zusammen mit einer bestätigten Adresse.

Der Versand läuft **nach der Antwort** (`defer()`). Ein SMTP-Handshake dauert
ein bis zwei Sekunden; synchron hinge die daran, die das Ereignis ausgelöst
hat — beim gemeldeten Anliegen also der Kunde. Eine Warteschlange bräuchte
einen Worker, den es hier nicht gibt. Scheitert der Versand, steht das im
Protokoll und die Glocke bleibt trotzdem stehen.

**Ausprobieren ohne Mailserver:** `MAIL_MAILER=log` (die Vorgabe) schreibt
jede Mail vollständig nach `storage/logs/laravel.log` — Betreff, Text, Link.
Es geht nichts hinaus.

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
