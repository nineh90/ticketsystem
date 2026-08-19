# ND-Deck

Das interne System von Nils-Digital. Struktur: **Kunde → Projekt → Ticket**.

Der Name ist Programm: an Bord gibt es mehrere Decks. Innen liegt die
**Brücke** (`/`) — dort wird gesteuert. Außen das **Passagierdeck**
(`/kunde`), auf dem unsere Kunden mitfahren. Kunden werden gesiezt und als
Gäste angesprochen; untereinander duzen wir uns.

Die Bezeichner in der Technik heißen weiterhin `ticketsystem` — Container,
Datenbank, Traefik-Router und die n8n-Adresse. Sie umzubenennen kostet eine
Wartungspause und bringt niemandem etwas, der sie ohnehin nie sieht.

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
| Brücke (intern) | `/` | Administratoren und Mitarbeiter |
| Passagierdeck | `/kunde` | Rolle `kunde`, je einem Kunden zugeordnet |

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

## Die Messe

`Kunden → der Kunde → Messe`, beim Kunden auf seiner Übersicht.

Ein Termin lebte bisher in einer Mail und in zwei Kalendern. Das reicht,
solange beide Seiten die Mail wiederfinden — und genau daran hakt es jedes
Mal ("wie war noch mal der Link?"). Jetzt steht er dort, wo der Kunde ohnehin
nachsieht, und trägt den Link bei sich.

**Die Videokonferenz bleibt draußen.** `url` zeigt heute auf Google Meet und
morgen auf etwas anderes; der Knopf beim Kunden heißt *An Bord gehen* und
führt dorthin, wo das Treffen gerade stattfindet. Ein eigener Raum wäre
später ein Adresswechsel, kein Umbau.

**Der Schalter *Einladen* ist die Einladung.** Vorgabe ist aus — wie beim
Tresor und den Dokumenten, aber aus einem anderen Grund: ein Termin entsteht
beim Planen, oft als Bleistiftstrich. Springt der Schalter an, geht die
Meldung hinaus (`TreffenObserver`). Danach melden nur noch zwei Dinge: ein
verschobener Termin und eine Absage. Eine getippte Zeile in der Tagesordnung
nicht — eine Meldung für jede Kleinigkeit ist eine, die bald übergangen wird.

**Abgesagt statt gelöscht.** Ein gelöschtes Treffen verschwindet wortlos aus
dem Bereich des Kunden, und er sitzt um zwei Uhr trotzdem davor. So bleibt es
durchgestrichen stehen, und der Kalendereintrag geht als `STATUS:CANCELLED`
hinaus, was ihn in fremden Kalendern wegräumt.

**Wer von uns dabei ist**, steht als eigene Liste am Treffen
(`treffen_user`). Wer dazukommt, bekommt eine Meldung — nur die Neuen, und
nie der, der sich gerade selbst eingetragen hat (`Support\Messe::crewSetzen`).
Die eigenen Termine stehen unter *Meine Wache*, alle unter *Brücke*.

### Der Kalendereintrag

`/treffen/{id}/kalender` bzw. `/kunde/treffen/{id}/kalender`, erzeugt von
`Support\Kalender`. Er entsteht bei jedem Abruf neu — ein verschobener Termin
ist hinter derselben Adresse sofort der richtige.

Zwei Dinge daran sind nicht offensichtlich, und beide entscheiden, ob der
Eintrag in fremden Programmen ankommt:

* **Zeiten gehen als UTC hinaus.** Die Anwendung rechnet in Ortszeit; ein
  Kalender ohne Zeitzonenangabe legt die Zeit des Lesers zugrunde. Ein Kunde
  in Wien bekäme den Termin sonst verschoben.
* **Die Kennung bleibt über Änderungen gleich**, die Sequenznummer wächst.
  Daran erkennt ein Kalenderprogramm, dass ein zweiter Eintrag derselbe
  Termin ist — sonst steht nach dem Verschieben beides drin und der Kunde
  erscheint zur alten Zeit.

### Wochenvorschau

Auf der Brücke, ganz oben. Sie sammelt aus vier Quellen ein, was in den
nächsten sieben Tagen ein Datum hat: Treffen, Meilensteine, Dokumentfristen
und fällige Tickets (`Support\Wochenplan`).

Der Gedanke: Termine sind längst da, sie liegen nur an vier Stellen. Wer
morgens wissen will, was diese Woche liegt, macht vier Listen auf oder keine.

**Jede Quelle geht durch ihr eigenes `sichtbarFuer`.** Das ist der Grund,
warum dort nichts abgekürzt wird — eine Übersicht, die "nur schnell" direkt
abfragt, ist die Stelle, an der ein Mitarbeiter den Kunden eines anderen zu
sehen bekommt, und niemand zählt eine Übersicht Zeile für Zeile nach.
Kommt eine weitere Sorte Termin dazu, bekommt sie dort eine Methode und
taucht damit überall auf, wo die Vorschau steht.

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

## Die maritime Sprache

Der Name des Systems ist **ND-Deck**, und die Bilder dazu sind nicht Zierat,
sondern eine Aufteilung: an Bord gibt es mehrere Decks. Wir stehen auf der
**Brücke** und fahren das Schiff, unsere Kunden sind **Passagiere**.

Die Grenze ist bewusst gezogen: **der Ton trägt den Rahmen, nicht die
Möbel.** Begrüßungen, Anmeldung, Mails und Leerzustände sind maritim.
*Projekte*, *Tickets*, *Dokumente* und *Rechnungen* heißen weiter so, wie sie
heißen — das sind die Wörter, die am Telefon fallen, und wer sie innen anders
nennt, übersetzt bei jedem Anruf im Kopf.

| Innen | Heißt | Weil |
|---|---|---|
| `/` | Meine Wache | was gerade auf dir liegt |
| `/betrieb` | Brücke | von dort wird gesteuert |
| Nachrichten | Funk | rein und raus, an keine Akte gebunden |
| Kunden | Passagiere | sie fahren mit |
| Abrechnung | Zahlmeister | führt an Bord die Kasse |
| Verwaltung | Maschinenraum | wo die Maschine eingestellt wird |
| Nutzer | Crew | kürzer als Mannschaft und geschlechtsneutral |

Die vier Betreuungsstände am Kunden sind mitgewandert und tragen ihre
Erklärung bei sich (`Betreuung::beschreibung()`, als Legende unter der
Auswahl und als Tooltip an der Spalte): **Am Kai** = Interessent, **An Bord**
= in Betreuung, **Vor Anker** = pausiert, **Von Bord** = beendet. Nur die
Beschriftung — die Werte in der Datenbank sind unverändert.

**Der Kundenbereich heißt weiter „Nils-Digital".** Der Name des Werkzeugs ist
unsere Angelegenheit; ein Passagier kennt die Reederei. Deshalb steht in
seiner Kopfzeile unser Logo samt Schriftzug, und auf seiner Übersicht „An
Bord von Nils-Digital" neben seinem eigenen Firmennamen.

Die Bezeichner in der Technik heißen weiterhin `ticketsystem` — Container,
Datenbank, Traefik-Router, `/docker/ticketsystem` und die n8n-Adresse. Sie
umzubenennen kostet eine Wartungspause, ein neues Backup-Ziel und einen
angepassten n8n-Aufruf, und bringt niemandem etwas, der sie nie sieht.

## Zwei Einstiegsseiten statt eines Dashboards

Intern gibt es zwei Startseiten, und die Trennlinie ist eine Frage: kann ich
daran etwas tun?

| | Adresse | Was darauf steht |
|---|---|---|
| Meine Wache | `/` | Meine Zahlen, meine Uhr, meine Treffen, ungelesene Nachrichten, meine Tickets, wartende Kundenanliegen |
| Brücke | `/betrieb` | Wochenvorschau, Zahlen des Betriebs, alle laufenden Uhren, Geschehen, offene Tickets je Kunde, erfasste Zeit je Kunde |

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
**Jeder wählt selbst, was er bekommt** — unter *Mein Zugang* (oben rechts im
Benutzermenü). Beim ersten Anmelden steht die Frage als Karte auf der Wache
(`MailEinrichten`): ja, nein, oder gleich selbst auswählen. Sie verschwindet
nach der Antwort, auch bei "nein" — ein Hinweis, der nach der Entscheidung
stehen bleibt, ist eine Aufforderung. Der Merker dafür ist
`benachrichtigungen_gefragt_at`, dieselbe Spalte wie beim Kunden.

Vorher stand die Auswahl ausschließlich unter *Maschinenraum → Crew*, also an
der Stelle, an der einer für einen anderen entscheidet, was der zu lesen
bekommt. Wer seine Mails nicht selbst gewählt hat, schaltet sie beim ersten
Ärger ganz ab — und danach erreicht ihn auch das nicht mehr, was ihn wirklich
angeht. Im Crew-Formular stehen die Felder trotzdem weiter: einen frisch
angelegten Zugang muss man einstellen können, bevor sich jemand das erste Mal
anmeldet.

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

### Kunden: erst bestätigen, dann schreiben

Ein Kundenzugang bekommt Mail **nur an eine Adresse, die er selbst genannt und
bestätigt hat** — nicht an die, mit der er sich anmeldet. Die haben wir beim
Anlegen eingetippt; sie ist manchmal geraten, manchmal ein geteiltes Postfach,
und niemand hat je geprüft, ob jemand sie liest.

Der Weg: auf der Übersicht steht die Frage (`BenachrichtigungenEinrichten`,
verschwindet nach der Antwort — auch bei „nein"). Unter *Mein Konto* nennt er
Adresse und Themen, bekommt einen signierten Link, und erst dessen Klick setzt
`benachrichtigungs_email_bestaetigt_at`. Ohne diesen Zeitstempel geht nichts
hinaus, gleich was am Schalter steht.

**Der Link trägt eine Prüfsumme über die Adresse.** Ändert der Kunde sie, wird
ein alter Link wertlos, obwohl seine Signatur noch gälte — sonst bestätigte
ein vertippter erster Versuch nachträglich eine Adresse, die gar nicht mehr
eingetragen ist. Die Bestätigungsmail ist die einzige, die an eine
unbestätigte Adresse geht, und deshalb die einzige ohne jeden Inhalt.

An beiden Stellen, an denen er die Adresse zu sehen bekommt, steht derselbe
Hinweis: **sie muss abrufbar sein.** Eine Adresse, auf die niemand zugreift,
sieht bis zum Bestätigungsklick genauso aus wie eine richtige — und danach
wartet jemand wochenlang auf Post, die nie kommt.

Nach dem Klick folgt eine **Begrüßungsmail** (`Willkommensmail`), sofern er dem
Versand zugestimmt hat. Sie ist keine Höflichkeit: sie beweist, dass der Weg
funktioniert, und nennt die gewählten Themen — die Gelegenheit, an der er
merkt, dass er etwas Falsches angehakt hat. Nur beim ersten Klick, weil
Mailprogramme Adressen manchmal von sich aus vorladen.

Worüber, wählt er selbst: `MailEreignis::fuerKunden()` — unsere Antwort und
der Stadienwechsel. Alles andere ist Betrieb; dass er selbst etwas gemeldet
hat, muss man ihm nicht mailen.

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
