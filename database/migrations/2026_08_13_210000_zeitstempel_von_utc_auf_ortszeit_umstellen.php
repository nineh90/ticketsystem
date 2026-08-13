<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Die bestehenden Zeitstempel von UTC auf Ortszeit umrechnen.
 *
 * Vorgeschichte: config/app.php hatte 'timezone' fest auf 'UTC' stehen und
 * las APP_TIMEZONE nie aus — obwohl es in der .env und im docker-compose des
 * Servers auf Europe/Berlin steht. Jeder bisher geschriebene Zeitstempel ist
 * deshalb UTC, während er als Ortszeit angezeigt wurde: im Sommer zwei
 * Stunden zu früh, im Winter eine.
 *
 * Ab der Korrektur in config/app.php schreibt die Anwendung Ortszeit. Damit
 * alte und neue Einträge dieselbe Bedeutung haben, müssen die alten einmalig
 * mitgezogen werden — sonst hätte der Verlauf eines Tickets an genau einer
 * Stelle einen Sprung von zwei Stunden.
 *
 * Die Umrechnung macht Postgres, nicht PHP: "AT TIME ZONE" kennt die
 * Sommerzeit-Umstellungen und rechnet jede Zeile mit dem Versatz um, der zu
 * ihrem eigenen Datum gehört. Ein pauschales "+2 Stunden" wäre für alles vor
 * dem 29.03. und nach dem 25.10. falsch.
 */
return new class extends Migration
{
    /** Die Zeitzone, in der dieses System arbeitet. */
    private const ZONE = 'Europe/Berlin';

    public function up(): void
    {
        $this->umrechnen(von: 'UTC', nach: self::ZONE);
    }

    public function down(): void
    {
        $this->umrechnen(von: self::ZONE, nach: 'UTC');
    }

    private function umrechnen(string $von, string $nach): void
    {
        // Nur Postgres kennt information_schema in dieser Form. Eine andere
        // Datenbank kommt hier nicht vor; die Abfrage würde aber auch nicht
        // still das Falsche tun, sondern gar nichts.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->zeitspalten() as $spalte) {
            // Datumsspalten wie tickets.faellig_am sind ausdrücklich nicht
            // dabei — sie tragen keine Uhrzeit, und ein Fälligkeitsdatum darf
            // durch eine Zeitzonenrechnung nicht auf den Vortag rutschen.
            DB::statement(sprintf(
                'update %s set %s = %s at time zone ? at time zone ? where %s is not null',
                $this->maskieren($spalte->table_name),
                $this->maskieren($spalte->column_name),
                $this->maskieren($spalte->column_name),
                $this->maskieren($spalte->column_name),
            ), [$von, $nach]);
        }
    }

    /** @return array<int, object> */
    private function zeitspalten(): array
    {
        return DB::select(
            "select table_name, column_name
               from information_schema.columns
              where table_schema = current_schema()
                and table_name not in ('migrations')
                and data_type = 'timestamp without time zone'
           order by table_name, column_name",
        );
    }

    private function maskieren(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
};
