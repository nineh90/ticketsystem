# Schnittstelle für n8n

Damit lassen sich Tickets von außen anlegen — gedacht für „Mail rein, Ticket
raus" bei Lerndex und Ähnlichem. In v1 ist nur die Schnittstelle gebaut, kein
Workflow.

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

```bash
php artisan ticket:token
```

Den Wert in die `.env` als `TICKET_API_TOKEN` eintragen, danach
`php artisan config:cache`. In n8n als Header mitgeben:

```
Authorization: Bearer <TOKEN>
```

Ist der Token in der Konfiguration leer, antwortet die Schnittstelle mit
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
| `absender_email` | optional | wird der Beschreibung vorangestellt |
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
    "url": "https://intern.nils-digital.de/tickets/6"
  },
  "neu": true
}
```

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

## Skizze für den Workflow

1. **Gmail/IMAP Trigger** — Postfach abfragen
2. **Switch** — nach Absender oder Betreff entscheiden, welches Projekt
3. **HTTP Request** — `POST http://ticketsystem/api/v1/tickets`, Header mit
   Token, `external_ref` auf die Message-ID
4. optional **Telegram** — Bescheid geben, dass ein Ticket entstanden ist

Das entspricht dem Muster aus
`lerndex_redesign/n8n/lerndex-formulare.workflow.json`, nur dass das Ziel
nicht Gmail plus Google Sheet ist, sondern dieses Ticketsystem.
