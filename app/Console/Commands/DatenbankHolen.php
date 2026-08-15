<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Holt einen Abzug der Live-Datenbank in die lokale Entwicklungsdatenbank.
 *
 * Die Richtung ist ausdrücklich nur diese: live → lokal. Es gibt bewusst
 * keinen Befehl in die Gegenrichtung. Live ist die Wahrheit; lokal wird
 * entwickelt und ausprobiert, und ein "spiel meine lokalen Daten live ein"
 * wäre genau der Knopf, der eines Abends versehentlich echte Kundendaten
 * überschreibt.
 */
class DatenbankHolen extends Command
{
    protected $signature = 'db:holen
                            {--host=187.124.178.193 : VPS}
                            {--container=ticketsystem : Name des App-Containers}';

    protected $description = 'Kopiert die Live-Datenbank in die lokale Entwicklungsdatenbank';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Nicht auf dem Produktivsystem ausführen.');

            return self::FAILURE;
        }

        $host = (string) $this->option('host');
        $lokal = config('database.connections.pgsql');

        $this->warn('Die lokale Datenbank "'.$lokal['database'].'" wird dabei vollständig ersetzt.');

        if (! $this->confirm('Fortfahren?', false)) {
            return self::SUCCESS;
        }

        $dump = storage_path('app/live-'.now()->format('Y-m-d-His').'.sql');

        $this->info('→ Abzug vom Server holen …');

        // pg_dump läuft im Postgres-Container auf dem VPS. --clean sorgt
        // dafür, dass der Abzug bestehende Tabellen vorher wegräumt; ohne das
        // kollidiert er mit dem lokalen Stand.
        $holen = Process::fromShellCommandline(
            sprintf(
                'ssh -o BatchMode=yes root@%s '.
                '"docker exec n8n-postgres-1 pg_dump -U ticketsystem -d ticketsystem --clean --if-exists" > %s',
                escapeshellarg($host),
                escapeshellarg($dump),
            ),
        );
        $holen->setTimeout(600);
        $holen->run(fn ($typ, $zeile) => $this->output->write($zeile));

        if (! $holen->isSuccessful() || ! is_file($dump) || filesize($dump) === 0) {
            $this->error('Der Abzug ist fehlgeschlagen.');
            @unlink($dump);

            return self::FAILURE;
        }

        $this->info('→ Lokal einspielen …');

        $einspielen = Process::fromShellCommandline(
            sprintf(
                'PGPASSWORD=%s psql -h %s -p %s -U %s -d %s -q < %s',
                escapeshellarg((string) $lokal['password']),
                escapeshellarg((string) $lokal['host']),
                escapeshellarg((string) $lokal['port']),
                escapeshellarg((string) $lokal['username']),
                escapeshellarg((string) $lokal['database']),
                escapeshellarg($dump),
            ),
        );
        $einspielen->setTimeout(600);
        $einspielen->run(fn ($typ, $zeile) => $this->output->write($zeile));

        if (! $einspielen->isSuccessful()) {
            $this->error('Das Einspielen ist fehlgeschlagen. Der Abzug liegt unter '.$dump);

            return self::FAILURE;
        }

        @unlink($dump);

        $this->newLine();
        $this->info('✓ Lokale Datenbank entspricht jetzt dem Live-Stand.');
        $this->comment('  Die Passwörter der Live-Konten gelten damit auch lokal.');
        $this->newLine();
        $this->warn('  Nicht mitgekommen sind die Zugangsdaten aus dem Tresor: sie sind');
        $this->warn('  mit dem APP_KEY des Servers verschlüsselt und hier nicht lesbar.');
        $this->warn('  Sie stehen als "nicht lesbar" da — das ist erwartet, kein Fehler.');

        return self::SUCCESS;
    }
}
