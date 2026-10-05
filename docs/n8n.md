# Schnittstelle für n8n und die Website

Damit lassen sich Tickets von außen anlegen — gedacht für „Mail rein, Ticket
raus" bei Lerndex und Ähnlichem. In v1 ist nur die Schnittstelle gebaut, kein
Workflow.

Zweiter Aufrufer ist die Website `nils-digital.de`: jede Anfrage aus
Kontaktformular und Projektfragebogen kommt als Ticket im Projekt `anfragen`
an (siehe „Die Website als Aufrufer").

## Adresse

n8n läuft auf demselben VPS und im selben Docker-Netz (`n8n_default`). Es
erreicht das Ticketsystem deshalb **intern**:

```
http://ticketsystem/api/v1/tickets
```

Kein Umweg übers öffentliche Internet, kein TLS für diesen Sprung, keine
Abhängigkeit davon, dass die Subdomain gerade erreichbar ist. Von außen ginge
auch `https://intern.nils-digital.de/api/v1/tickets`, dafür gibt es hier aber
keinen Grund.

## Token

Es gibt zwei, einen je Aufrufer:

| Variable | Aufrufer | Quelle am Ticket | Erzeugen |
|---|---|---|---|
| `TICKET_API_TOKEN` | n8n | `api` („Schnittstelle") | `php artisan ticket:token` |
| `TICKET_API_TOKEN_WEBSITE` | Website | `website` („Website") | `php artisan ticket:token --website` |

Den Wert in die `.env` eintragen (auf dem Server `deploy/.env`), danach
`php artisan config:cache` bzw. den Container neu starten. Im Aufruf als
Header mitgeben:

```
Authorization: Bearer <TOKEN>
```

Beide Token öffnen dieselben Adressen. Der Unterschied ist die **Quelle**,
die das Ticket trägt: sie folgt dem Token und lässt sich nicht über ein Feld
im Aufruf setzen. In der Ticketliste lässt sich danach filtern.

Widerrufen heißt: die Zeile leeren oder neu belegen. Der andere Token gilt
unverändert weiter. Sind **beide** leer, antwortet die Schnittstelle mit
`503` — sie steht also nach einem unvollständigen Deploy nicht offen.

## `GET /api/v1/projects`

Liefert die nicht abgeschlossenen Projekte, damit ein Workflow einen Slug auf
ein Projekt abbilden kann.

```json
{
  "projekte": [
    { "id": 1, "slug": "website", "name": "Website", "kunde": "Lerndex", "kuerzel": "LDX" }
  ]
}
```

## `POST /api/v1/tickets`

| Feld | | Beschreibung |
|---|---|---|
| `projekt` | **Pflicht** | Slug (`website`) oder ID (`1`) |
| `titel` | **Pflicht** | max. 255 Zeichen |
| `beschreibung` | optional | Freitext |
| `prioritaet` | optional | `niedrig` \| `normal` \| `hoch` \| `dringend` (Vorgabe `normal`) |
| `external_ref` | optional | **dringend empfohlen**, siehe unten |
| `absender_email` | optional | wird gespeichert und der Beschreibung vorangestellt |
| `absender_name` | optional | max. 255 Zeichen; wird gespeichert und der Beschreibung vorangestellt |
| `faellig_am` | optional | Datum |

Beispiel:

```bash
curl -X POST https://intern.nils-digital.de/api/v1/tickets \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "projekt": "website",
    "titel": "Kontaktformular meldet einen Fehler",
    "beschreibung": "Beim Absenden erscheint eine leere Seite.",
    "absender_email": "kunde@example.de",
    "prioritaet": "hoch",
    "external_ref": "mail-<message-id>"
  }'
```

Antwort `201`:

```json
{
  "ticket": {
    "id": 6, "kennung": "LDX-5", "titel": "…", "status": "Backlog",
    "prioritaet": "hoch", "kunde": "Lerndex", "projekt": "Website",
    "url": "https://intern.nils-digital.de/tickets/ldx-5-neue-anmeldung-klemmt"
  },
  "neu": true
}
```

Die Adresse entsteht immer aus `APP_URL`, nicht aus dem Host des Aufrufs —
auch wer intern `http://ticketsystem/…` ruft, bekommt die öffentliche Adresse
zurück, die sich im Browser öffnen lässt.

Sie trägt Kennung und Titel, damit man in einer Mail schon vor dem
Klick sieht, worum es geht. Aufgelöst wird nur über die Kennung: ein
umbenanntes Ticket bleibt unter der alten Adresse erreichbar, und die frühere
Form `/tickets/6` funktioniert ebenfalls weiter.

### `external_ref` — bitte immer mitgeben

Der Wert ist eindeutig. Kommt derselbe noch einmal an, wird **kein zweites
Ticket angelegt**; die Antwort ist dann `200` mit `"neu": false` und dem
bestehenden Ticket.

Das ist nicht Feinschliff, sondern notwendig: n8n wiederholt Aufrufe bei
Zeitüberschreitung. Ohne `external_ref` entsteht bei jedem Wiederholungslauf
ein Duplikat, und bei einem Postfach-Abgleich, der stündlich läuft, ist das
binnen eines Tages ein unbenutzbares System.

Als Wert eignet sich die `Message-ID` der Mail — sie ist ohnehin eindeutig und
bleibt über Wiederholungen hinweg gleich.

## Fehlerantworten

| Code | Bedeutung |
|---|---|
| `401` | Token fehlt oder stimmt nicht |
| `422` | Projekt unbekannt, oder Pflichtfeld fehlt |
| `429` | mehr als 60 Aufrufe je Minute |
| `503` | kein Token eingerichtet, oder es gibt keine Ticket-Stadien |

## `GET /api/v1/uebersicht`

Was im Ticketsystem los ist — für das Dashboard in der Redaktion der Website.
Nur lesend, hinter demselben Token-Schutz (beide Token gelten).

```json
{
  "offen": 12,
  "wartet_auf_uns": 3,
  "anfragen_offen": 2,
  "neueste": [
    {
      "kennung": "ANF-4",
      "titel": "Neue Website für den Verein",
      "status": "Backlog",
      "kunde": "Eingang",
      "url": "https://intern.nils-digital.de/tickets/anf-4-neue-website-fuer-den-verein",
      "geaendert_am": "2026-10-04T21:40:00+02:00"
    }
  ]
}
```

| Feld | Bedeutung |
|---|---|
| `offen` | alle Tickets, die nicht in einem abschließenden Stadium stehen |
| `wartet_auf_uns` | offen, von außen gekommen (Quelle `kunde` oder `website`) und noch ohne Antwort von uns |
| `anfragen_offen` | offene Tickets im Projekt `anfragen` |
| `neueste` | die zuletzt geänderten Tickets, **höchstens zehn**, jüngstes zuerst; ohne Tickets eine leere Liste |
| `neueste[].url` | öffentliche Adresse aus `APP_URL` |
| `neueste[].geaendert_am` | ISO 8601 mit Zeitzone |

Die drei Zahlen sind immer ganze Zahlen, nie `null`. Die Website wird genau
gegen diese Form gebaut: nichts umbenennen, Neues nur dazustellen.

`wartet_auf_uns` folgt derselben Regel wie die stündliche Erinnerung des
Planers (`Ticket::scopeWartetAufUns`): „Antwort" ist ein Kommentar von uns,
bei dem „Interne Notiz" ausgeschaltet ist. Eine interne Notiz zählt nicht.
Der einzige Unterschied: die Übersicht zählt ab der ersten Minute, gemahnt
wird erst nach 24 Stunden. Das gilt auch für Anfragen der Website — wer per
Mail geantwortet hat, hält das am Ticket mit einem nicht-internen Kommentar
fest oder schließt es, sonst zählt es weiter.

## Die Website als Aufrufer

Die Website ruft `POST http://ticketsystem/api/v1/tickets` mit dem Token aus
`TICKET_API_TOKEN_WEBSITE` und schickt:

| Feld | Inhalt |
|---|---|
| `projekt` | `anfragen` |
| `titel` | Betreff und Name |
| `beschreibung` | Name, Herkunft, Nachricht, Link in die Redaktion der Website |
| `absender_email` | Adresse der anfragenden Person |
| `absender_name` | ihr Name (optional) |
| `external_ref` | `website-anfrage-{id}` |

Aus der Antwort liest sie `ticket.kennung` und `ticket.url` und speichert
beides an der Anfrage. Diese beiden Felder, die Wiedererkennung über
`external_ref` und die Felder oben sind damit Vertrag: neue Felder nur
optional, nichts davon umbenennen.

Das Projekt `anfragen` gehört zum Kunden „Eingang" (Kürzel `ANF`) und ist im
Kundenbereich nicht sichtbar.

### Aus einer Anfrage wird ein Kunde

Am Ticket steht für Administratoren der Knopf **„Als Interessent anlegen"**,
sobald ein Absender hinterlegt ist. Er legt einen Kunden mit dem Stand
„Am Kai" (`interessent`) und einen Kontakt mit Name und Adresse an; beides
ist aus dem Ticket vorbelegt, das Kürzel wird vorgeschlagen.

Ist die Absenderadresse schon bei einem Kunden bekannt (als Kontakt oder als
Adresse des Kunden), wird stattdessen die Zuordnung zu diesem Kunden
angeboten.

**Das Ticket zieht dabei nicht um.** Es bleibt `ANF-…` mit derselben Adresse
— die Website hat beides gespeichert. Ticket und Kunde verweisen aufeinander
(`tickets.interessent_id`): am Ticket steht „Daraus wurde", in der Kundenakte
„Entstanden aus".

## Skizze für den Workflow

1. **Gmail/IMAP Trigger** — Postfach abfragen
2. **Switch** — nach Absender oder Betreff entscheiden, welches Projekt
3. **HTTP Request** — `POST http://ticketsystem/api/v1/tickets`, Header mit
   Token, `external_ref` auf die Message-ID
4. optional **Telegram** — Bescheid geben, dass ein Ticket entstanden ist

Das entspricht dem Muster aus
`lerndex_redesign/n8n/lerndex-formulare.workflow.json`, nur dass das Ziel
nicht Gmail plus Google Sheet ist, sondern dieses Ticketsystem.
