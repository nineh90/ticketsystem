<?php

namespace App\Console\Commands;

use App\Support\Wache;
use Illuminate\Console\Command;

/**
 * Stündlich: wartet draußen jemand zu lange auf unsere erste Antwort?
 *
 * Die einzige dieser Meldungen, die etwas misst, das wir versprochen haben.
 * Alles andere ist unser eigener Haushalt.
 */
class KundeWartet extends Command
{
    protected $signature = 'wache:kundewartet';

    protected $description = 'Meldet Anliegen, die seit über '.Wache::ANTWORT_SPAETESTENS_STUNDEN.' Stunden ohne Antwort sind';

    public function handle(): int
    {
        $anzahl = Wache::kundeWartet();

        defer()->invoke();

        $this->info($anzahl === 0 ? 'Niemand wartet zu lange.' : $anzahl.' Anliegen gemeldet.');

        return self::SUCCESS;
    }
}
