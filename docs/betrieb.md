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

Über *Verwaltung → Nutzer*. Zwei Dinge sind zu beachten:

- **Zugang freigegeben** muss an sein, sonst kommt die Person trotz gültigem
  Konto nicht ins Dashboard.
- **Projekte zuordnen** — Mitarbeiter sehen ausschließlich Projekte, in denen
  sie stehen. Administratoren sehen ohnehin alles.

Jeder ändert Name und Passwort danach selbst unter *Profil* (oben rechts).

Ausgeschiedene Mitarbeiter **deaktivieren statt löschen** — sonst verlieren
ihre Tickets, Kommentare und Zeitbuchungen die Zuordnung.

## Was noch offen ist

- **Mailversand.** `MAIL_MAILER` steht auf `log`. Deshalb ist auch
  „Passwort vergessen" nicht aktiv — der Knopf verschickt sonst eine Mail, die
  nirgends ankommt. Bis dahin setzt ein Admin ein vergessenes Passwort in der
  Nutzerverwaltung neu. Sobald Strato-SMTP hinterlegt ist:
  `->passwordReset()` im `AdminPanelProvider` einkommentieren.
- **n8n-Anbindung.** Die Schnittstelle steht und ist geprüft, es gibt nur noch
  keinen Workflow. Siehe `docs/n8n.md`.
- **Anhänge an Tickets.** Datenmodell und `php.ini` sind darauf vorbereitet
  (16 MB), die Oberfläche fehlt.
- **Kundenbereich.** Vorbereitet über `comments.ist_intern` und
  `users.customer_id`; wird ein zweites Filament-Panel.

## Beobachtung am Rande

`kein-einzelfall-scheduler` läuft seit 13 Tagen als *unhealthy*. Hat mit dem
Ticketsystem nichts zu tun, fiel aber beim Durchsehen der Container auf.
