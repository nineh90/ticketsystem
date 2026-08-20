<?php

namespace App\Console\Commands;

use App\Support\Kasse;
use Illuminate\Console\Command;

/** Zum Monatsanfang: welche Zeit noch auf keiner Rechnung steht. */
class Monatsmeldung extends Command
{
    protected $signature = 'kasse:monat';

    protected $description = 'Meldet, wie viel abrechenbare Zeit noch offen ist';

    public function handle(): int
    {
        $anzahl = Kasse::monatsmeldung();

        defer()->invoke();

        $this->info($anzahl === 0 ? 'Nichts offen.' : 'An '.$anzahl.' gemeldet.');

        return self::SUCCESS;
    }
}
