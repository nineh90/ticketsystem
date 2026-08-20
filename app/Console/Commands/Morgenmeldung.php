<?php

namespace App\Console\Commands;

use App\Support\Wache;
use Illuminate\Console\Command;

/** Morgens um acht: was heute an mir hängt. Siehe Support\Wache. */
class Morgenmeldung extends Command
{
    protected $signature = 'wache:morgenmeldung';

    protected $description = 'Meldet jedem, was heute aus seinen Tickets fällig ist';

    public function handle(): int
    {
        $anzahl = Wache::morgenmeldung();

        // Der Versand der Mails hängt an defer() und liefe sonst erst nach
        // der Antwort — die es bei einem Befehl nicht gibt.
        defer()->invoke();

        $this->info($anzahl === 0 ? 'Niemand hat heute etwas offen.' : 'An '.$anzahl.' gemeldet.');

        return self::SUCCESS;
    }
}
