# Betrieb

Live unter <https://intern.nils-digital.de> auf dem Hostinger-VPS
`187.124.178.193`.

## Wie es dort liegt

```
/docker/ticketsystem/            Git-Klon von origin/main
  deploy/.env                    APP_KEY, DB_PASSWORD, TICKET_API_TOKEN (nur dort, chmod 600)
/usr/local/bin/deploy-ticketsystem     Kopie von deploy/deploy.sh
/usr/local/bin/ticketsystem-backup     Kopie von deploy/backup.sh
/var/log/ticketsystem-deploy.log
/var/backups/ticketsystem/       täglich, 14 Stände
```

Der Container heißt `ticketsystem`, hängt im Netz `n8n_default` und
veröffentlicht **keinen Port** — Traefik aus dem n8n-Stack ist der einzige Weg
hinein.

> ⚠️ `docker compose down` in `/docker/n8n` nimmt Traefik **und** Postgres mit —
> also auch das Ticketsystem, kein-einzelfall und fahrlehrerinsarah.

## Datenbank

Eigene Datenbank `ticketsystem` mit eigenem Benutzer im vorhandenen Container
`n8n-postgres-1`. n8n kommt damit nicht an die Ticketdaten und umgekehrt.

```bash
docker exec -it n8n-postgres-1 psql -U ticketsystem -d ticketsystem
```

## Deploy

Push auf `main` → GitHub Action → Tests → SSH → `deploy-ticketsystem`.

Der Schlüssel dafür ist in `/root/.ssh/authorized_keys` per erzwungenem
Kommando auf genau dieses Skript festgenagelt; eine Shell bekommt man damit
nicht (nachgeprüft). Er gilt nur für dieses Projekt.

Von Hand geht auch:

```bash
ssh root@187.124.178.193 /docker/ticketsystem/deploy/deploy.sh
```

Migrationen und Zwischenspeicher erledigt das Einstiegsskript beim Start des
neuen Containers. **Kein Seeder, der Daten ersetzt** — nur die Ticket-Stadien
über `firstOrCreate`, umbenannte Stadien bleiben also erhalten.

## Backup

Täglich um 3:30 Uhr, komprimiert, mit `gzip -t` geprüft, 14 Stände.

```bash
/usr/local/bin/ticketsystem-backup          # von Hand
ls -la /var/backups/ticketsystem/
```

Wiederherstellen:

```bash
gunzip -c /var/backups/ticketsystem/ticketsystem-<stand>.sql.gz \
  | docker exec -i n8n-postgres-1 psql -U ticketsystem -d ticketsystem
```

> Das Backup liegt auf derselben Maschine wie die Datenbank. Gegen einen
> Ausfall des VPS schützt es damit **nicht**. Wenn das wichtig wird: einen
> Cronjob auf dem Arbeitsrechner, der den jüngsten Stand per `scp` abholt.

## Daten von live nach lokal

```bash
php artisan db:holen
```

Die Gegenrichtung gibt es bewusst nicht: live ist die Wahrheit, lokal wird
entwickelt. Ein „spiel meine lokalen Daten live ein" wäre der Knopf, der eines
Abends versehentlich echte Kundendaten überschreibt.

## Nutzer anlegen

Über *Maschinenraum → Crew*. Zwei Dinge sind zu beachten:

- **Zugang freigegeben** muss an sein, sonst kommt die Person trotz gültigem
  Konto nicht ins Dashboard.
- **Projekte zuordnen** — Mitarbeiter sehen ausschließlich Projekte, in denen
  sie stehen. Administratoren sehen ohnehin alles.

Jeder ändert Name, Passwort **und seine Mail-Themen** danach selbst unter
*Mein Zugang* (oben rechts). Beim ersten Anmelden wird er auf der Wache
gefragt, ob er überhaupt Mail will.

Die Felder im Crew-Formular bleiben trotzdem: ein frisch angelegter Zugang
soll eingestellt sein, bevor sich jemand das erste Mal anmeldet.

> Achtung bei **neuen** Ereignistypen: ist am Zugang schon eine Auswahl
> gespeichert, sind später hinzugekommene Typen darin nicht enthalten und
> gehen still nicht hinaus. Wer alles will, hakt sie einmal nach. Nur eine
> nie angefasste Auswahl (`null`) lässt alles durch.

Ausgeschiedene Mitarbeiter **deaktivieren statt löschen** — sonst verlieren
ihre Tickets, Kommentare und Zeitbuchungen die Zuordnung.

## Kundenzugänge

*Kunden → der Kunde → Zugänge → Zugang anlegen.* Rolle und Kundenzuordnung
setzt das Formular selbst; ein Startpasswort wird vorgeschlagen (drei Wörter
und eine Zahl, damit man es am Telefon vorlesen kann).

Weiterzugeben sind drei Dinge: die E-Mail-Adresse, das Startpasswort und die
Adresse <https://intern.nils-digital.de/kunde>. Ob er sich schon angemeldet
hat, steht in der Spalte *Zuletzt angemeldet* — „noch nie" heißt in aller
Regel, dass die Weitergabe nicht angekommen ist.

Vergessenes Passwort: Knopf *Passwort neu setzen* an der Zeile. Ausgeschiedene
Ansprechpartner **deaktivieren statt löschen** — sonst verlieren ihre Meldungen
und Antworten die Zuordnung.

**Zugeteilte Passwörter müssen gewechselt werden.** Setzt jemand anderes als
der Kontoinhaber ein Passwort, bekommt das Konto `passwort_wechseln = true`
und landet beim nächsten Aufruf auf seinem Profil, bis es ein eigenes hat.
Das gilt bei jedem zugeteilten Passwort, nicht nur beim ersten — auch das
fünfte ist durch einen Chatverlauf gegangen. Die Regel steht in
`User::booted()` und greift damit für jede Stelle, die Passwörter setzt; die
Umleitung macht `PasswortWechseln` (in beiden Panels registriert). In der
Spalte *Startpasswort* der Zugangsliste sieht man, wer noch eines nutzt.

Wechselt der Kunde sein Passwort, muss er es zweimal eingeben und zusätzlich
das alte bestätigen. Danach landet er auf seiner Übersicht, nicht wieder im
Formular.

### Die Kundenakte

Am Kunden hängen inzwischen mehr als Name und Farbe:

- **Betreuung** — Stand der Beziehung, Vertragsart, Laufzeit, Kündigungsfrist.
  Rein intern, der Kunde sieht davon nichts.
- **Technik** — Website, Hoster und die **Demo-Adresse**. Sie wird genommen,
  wie sie dasteht: `kein-einzelfall.nils-digital.de` ergibt genau das, es
  wird nichts davorgehängt. Hat ein Kunde mehrere Projekte mit je eigener
  Adresse, schreibt man `{projekt}` an die Stelle, an der das Projektkürzel
  stehen soll — `{projekt}.nils-digital.de` oder auch
  `https://nils-digital.de/demo/{projekt}`. Im Projektformular setzt der
  Knopf neben *Vorschau* das Ergebnis ein; eingesetzt, nicht automatisch
  übernommen, denn eine Adresse, unter der nichts läuft, wäre dem Kunden
  gegenüber ein toter Knopf.

  Der erste Anlauf hat hier eine „Basis" erwartet und das Projektkürzel
  davorgehängt — aus `kein-einzelfall.nils-digital.de` wurde damit
  `kein-einzelfall.kein-einzelfall.nils-digital.de`. Eine Regel, die
  stillschweigend etwas in eine Adresse einfügt, ist immer für die Hälfte
  der Fälle falsch.
- **Rechnungsdaten** — Anschrift, USt-IdNr., abweichende Rechnungsadresse.
- **Logo** — erscheint als Avatar neben jedem Beitrag dieses Kunden und in
  den Listen; ohne Logo bleiben es die Initialen. Mitarbeiter bekommen nie
  eins: das Bild trägt die Aussage „hier schreibt jemand von dort".

  Anders als Ticket-Anhänge liegt es auf der **öffentlichen** Platte
  (`storage/app/public`, ausgeliefert unter `/storage/…`) — es erscheint
  vielfach je Seite, und über eine geschützte Route wäre jedes dieser
  Bilder eine eigene PHP-Anfrage. Die Dateinamen vergibt Filament zufällig.
  Das Verzeichnis liegt im Volume `ticketsystem-storage` und übersteht
  Deploys; den Symlink legt `deploy/entrypoint.sh` bei jedem Start neu an.
- **Kontakte** — die Menschen beim Kunden, unabhängig davon, ob sie einen
  Zugang haben. Der Buchhalter braucht keinen, seine Mailadresse aber schon.
  Ein Zugang kann auf einen Kontakt zeigen (Feld *Ist welcher Kontakt?*).
- **Zugangsdaten** — der Tresor, siehe unten.
- **Dokumente** — Angebote, Rechnungen und Verträge als PDF, siehe unten.

Der Kunde pflegt Anschrift, Rechnungsadresse, USt-IdNr., Website und seine
Telefonnummer unter *Mein Konto* selbst; wir bekommen darüber eine
Benachrichtigung. Firmenname, Kürzel, Betreuung und Vertrag kann er nicht
ändern — am Namen hängen die Ticketnummern.

*Mein Konto* steht dabei im **Anzeigemodus**; zum Ändern gibt es oben rechts
einen Knopf. Die meisten Aufrufe sind ein Nachsehen ("stimmt die Anschrift
noch?"), und ein sofort offenes Formular mit dreizehn Feldern beantwortet die
falsche Frage. Nur beim erzwungenen Passwortwechsel steht das Formular gleich
da — dort ist es der Zweck der Seite.

Die alten Spalten `customers.ansprechpartner/email/telefon` stehen weiterhin
in der Datenbank und wurden beim Umstieg in die Kontakte **kopiert**, nicht
verschoben. Sie sind nur aus dem Formular verschwunden.

### Dokumente: Angebote, Rechnungen, Verträge

*Kunden → der Kunde → Dokumente*. Die PDF kommt fertig aus **sevDesk** und
wird hier nur abgelegt — hier entsteht keine Rechnung. Daneben stehen Art,
Titel, Nummer, Datum, Frist, Betrag und Stand; alles Weitere steht im PDF.

Die Dateien liegen auf der Platte `local`, also **außerhalb von `public/`**,
und werden nur über `/dokument/{id}` bzw. `/kunde/dokument/{id}` gegen die
`DokumentPolicy` ausgeliefert. Das wiegt hier schwerer als bei Ticket-Anhängen:
in einem Angebot stehen Preise, und Dateinamen aus einem Buchhaltungsprogramm
sind fortlaufend und damit erratbar. Der abgelegte Name bekommt deshalb einen
Zufallsvorsatz, der echte Name steht hinter `__`.

**Freigabe je Dokument, Vorgabe aus** — wie beim Tresor. Ein vergessener
Schalter führt dazu, dass der Kunde etwas nicht sieht, nie dazu, dass ein
Entwurf bei ihm landet. Der Bereich *Dokumente* taucht in seinem Menü
überhaupt erst auf, wenn mindestens ein Dokument freigegeben ist.

**Zeiten zuordnen.** An einer Rechnung sagt der Knopf *Zeiten zuordnen*,
welche Buchungen damit abgegolten sind. Sie verschwinden danach aus
`/abrechnung`. Abwählen löst die Zuordnung wieder; eine gelöschte Rechnung
gibt ihre Zeiten ebenfalls frei. Der Kunde sieht davon nur die Summe
(„Enthaltene Arbeitszeit"), nie die einzelnen Buchungen.

**Angebote kann der Kunde annehmen oder ablehnen.** Seine Antwort wird mit
Zeitstempel und Person festgehalten; steht dort nichts, haben wir den Stand
selbst eingetragen. Wir erfahren es über die Glocke *und* im Ereignisstrom
unter *Betrieb*.

Löschen nimmt die Datei mit — und darf nur der Administrator oder wer sie
hochgeladen hat.

### Zugangsdaten-Tresor

*Kunden → der Kunde → Zugangsdaten*, oder am Projekt derselbe Reiter für
dessen eigene Zugänge. Passwörter liegen mit Laravels `encrypted`-Cast
verschlüsselt, also über den `APP_KEY`. **Ein Wechsel des APP_KEY macht alle
Einträge unlesbar** — das ist der Preis dafür, dass in den nächtlichen
Datenbankabzügen keine Klartext-Passwörter stehen.

**Auf einer Kopie der Datenbank sind die Passwörter nicht lesbar.** Die
Entwicklungsumgebung hat einen eigenen APP_KEY; nach `db:holen` stehen alle
Tresoreinträge als *nicht lesbar* da. Das ist erwartet und kein Fehler — und
seit einem Absturz auf genau diesem Weg reißt es auch keine Seite mehr mit:
unlesbar ergibt `null`, nicht eine Ausnahme (`Zugangsdaten::passwort`).

Je Eintrag entscheidet ein Schalter *Der Kunde darf das sehen*. Vorgabe ist
**aus**: ein vergessener Schalter führt dazu, dass der Kunde etwas nicht
sieht, nie umgekehrt. Freigegebene Einträge stehen bei ihm unter
*Zugangsdaten* (allgemeine) bzw. auf der Projektseite (projektbezogene), mit
Kopierknopf und Aufdecken auf Klick.

Zugänge zu einem Projekt, das auf *nicht kundensichtbar* steht, bleiben auch
dann verborgen, wenn sie selbst freigegeben sind.

### Projektphase und Meilensteine

Am Projekt gibt es jetzt zwei Zustände nebeneinander:

- **Status (intern)** — aktiv/pausiert/abgeschlossen, unsere Ablage.
- **Phase** — Konzept → Umsetzung → Abnahme → Live → Betreuung. Das ist, was
  der Kunde als „Stand" liest, samt einem erklärenden Satz darunter.

Dazu **zwei Adressen**: `demo_url` (Vorschau) und `live_url` (die echte).
Welche der Knopf beim Kunden öffnet, entscheidet die Phase — ab *Live* die
eigene Adresse, davor die Vorschau; fehlt die passende, nimmt er die andere.

**Meilensteine** (Reiter am Projekt) ergeben beim Kunden einen Zeitstrahl.
Der Fortschritt in Prozent wird daraus gerechnet — erledigte durch alle
kundensichtbaren —, nicht getippt. Ohne Meilensteine erscheint gar kein
Balken. Abhaken geht über den Knopf *Abhaken* an der Zeile.

Die **Reihenfolge** ändert man über *Reihenfolge ändern* im Kopf der Liste:
der Knopf schaltet das Ziehen an, danach *Reihenfolge fertig*. Neue Punkte
hängen sich von selbst hinten an.

*Aus Vorlage* legt den üblichen Satz Punkte auf einen Schlag an — die
Vorlagen (Website, App, Betreuung) stehen unter *Maschinenraum →
Reiseplan-Vorlagen* und werden **dort** geändert, ohne Deploy. Bis zum
19.08.2026 lagen sie in `config/meilensteine.php`; der Umzug hatte genau
diesen Grund: die Texte stehen wörtlich beim Kunden, und wer sie ändern
will, soll dafür keinen Entwickler brauchen. Die Website-Vorlage ist der Zeitstrahl aus dem
KE!N-EINZELFALL-Projekt, Texte inklusive. Angehängt wird immer hinten;
nichts wird ersetzt oder gelöscht. Was schon dasteht, ist in der Auswahl
abgehakt und mit *steht schon da* markiert, auch wenn es etwas anders heißt
(„Angebot" gilt als „Erstellung eines Angebots").

**Die Vorlage ist nur eine Starthilfe.** Ab dem Anlegen gehört jeder
Meilenstein dem Projekt allein: Titel, Text, Termin, Haken, Sichtbarkeit und
Reihenfolge lassen sich bei jedem Kunden einzeln und jederzeit ändern,
Punkte kommen dazu oder fallen weg. Eine später geänderte Vorlage schreibt
laufende Projekte nicht um — und was bei einem Kunden geändert wird, bewegt
bei keinem anderen etwas.

Was der Kunde sieht, hängt an zwei Schaltern:

- **Projekt → Kundenbereich → Für den Kunden sichtbar.** Aus: das Projekt
  verschwindet aus seinem Bereich, samt aller Anliegen dazu.
- **Kommentar → Interne Notiz.** Aus: der Kunde liest den Kommentar als
  Antwort und wird darüber benachrichtigt.

Zeitbuchungen, Budget, Priorität, Zuständigkeit und Termine sieht er nie.

Screenshots kann der Kunde direkt beim Melden mitschicken und später am
Anliegen unter *Dateien* nachreichen; intern erscheinen sie als gewöhnliche
Anhänge am Ticket. Uploads aus abgebrochenen Formularen liegen bis zu einem
Tag unter `storage/app/anhaenge/eingang` und werden beim nächsten Aufruf des
Meldeformulars gelöscht — ohne Scheduler geht das nur so.

Ein Stadium mit *Der Kunde ist am Zug* (unter Ticket-Stadien) benachrichtigt
ihn beim Wechsel dorthin und stellt das Anliegen in seinem Bereich ganz oben
unter „Sie sind am Zug". Voreingestellt ist das bei *Warten auf Kunde*.

## Was noch offen ist

- **Mailversand.** `MAIL_MAILER` steht auf `log`, es geht also nichts hinaus.
  Die Anwendung ist vorbereitet: Meldungen gehen als Mail hinaus, sobald an
  einem Zugang *E-Mail bei Meldungen* gesetzt ist **und** Zugangsdaten
  hinterlegt sind.

  **Scharf geschaltet wird ausschließlich über `/docker/ticketsystem/deploy/.env`
  auf dem Server** — dieselbe Datei, in der APP_KEY und DB_PASSWORD stehen.
  Die `docker-compose.yml` liest die Werte von dort und fällt ohne sie auf
  `log` zurück; in ihr steht nie ein Passwort, sie liegt im Repository.

  ```
  MAIL_MAILER=smtp
  MAIL_HOST=smtp.strato.de
  MAIL_PORT=587
  MAIL_USERNAME=info@nils-digital.de
  MAIL_PASSWORD=…
  MAIL_SCHEME=smtp
  MAIL_FROM_ADDRESS=info@nils-digital.de
  ```

  `MAIL_SCHEME` kennt genau zwei Werte: **`smtp`** (Port 587, STARTTLS) und
  **`smtps`** (Port 465). Ein `tls` gibt es nicht — das ist die alte
  Schreibweise aus `MAIL_ENCRYPTION` und wird mit *„The tls scheme is not
  supported"* abgewiesen. Der Fehler steht dann im Protokoll, die Glocke
  bleibt davon unberührt.

  **Absender und Postfach müssen zusammenpassen.** Strato lässt nur senden,
  wer sich mit demselben Postfach anmeldet — ein Absender, den es nicht gibt,
  wird abgewiesen. `ticketsystem@nils-digital.de` gibt es nicht; Vorgabe ist
  deshalb `info@nils-digital.de`. Kommt später ein eigenes Postfach dazu,
  genügt die Zeile in `deploy/.env`, ohne Änderung am Code.

  Danach `docker compose up -d` in `/docker/ticketsystem/deploy`. Ein Deploy
  über Push tut dasselbe.

  Ebenfalls noch aus: „Passwort vergessen" in beiden Panels — der Knopf
  verschickt sonst eine Mail, die nirgends ankommt. Bis dahin setzt ein Admin
  ein vergessenes Passwort in der Nutzerverwaltung neu. Sobald der Versand
  steht: `->passwordReset()` in den Panel-Providern einkommentieren.
- **n8n-Anbindung.** Die Schnittstelle steht und ist geprüft, es gibt nur noch
  keinen Workflow. Siehe `docs/n8n.md`.
- **Kontaktdaten im Kundenbereich.** `config/kontakt.php` liest
  `KONTAKT_TELEFON` aus der `.env` — solange die Variable fehlt, steht auf der
  Kontaktseite keine Telefonnummer.
- **Kein Queue-Worker.** `QUEUE_CONNECTION=database`, aber nichts arbeitet die
  Warteschlange ab. Benachrichtigungen umgehen sie deshalb bewusst (siehe
  README). Wer künftig etwas in die Warteschlange legt, muss entweder einen
  Worker einrichten oder denselben Weg gehen.

## Beobachtung am Rande

`kein-einzelfall-scheduler` läuft seit 13 Tagen als *unhealthy*. Hat mit dem
Ticketsystem nichts zu tun, fiel aber beim Durchsehen der Container auf.
