<?php

namespace App\Console\Commands;

use App\Support\Wache;
use Illuminate\Console\Command;

/** Montags um sieben: was diese Woche für mich ansteht. Siehe Wache::wochenplan. */
class Wochenplan extends Command
{
    protected $signature = 'wache:wochenplan';

    protected $description = 'Schickt jedem seine Woche: Treffen, fällige Tickets, Meilensteine';

    public function handle(): int
    {
        $anzahl = Wache::wochenplan();

        $this->info($anzahl === 0 ? 'Niemand hat diese Woche etwas geplant.' : 'An '.$anzahl.' verschickt.');

        return self::SUCCESS;
    }
}
