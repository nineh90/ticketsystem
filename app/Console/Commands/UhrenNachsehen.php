<?php

namespace App\Console\Commands;

use App\Support\Wache;
use Illuminate\Console\Command;

/**
 * Abends: wer hat noch eine Uhr laufen?
 *
 * Bewusst nur eine Erinnerung. Automatisch stoppen hieße, eine Aussage über
 * geleistete Arbeit selbst zu schreiben — das tut das System nicht.
 */
class UhrenNachsehen extends Command
{
    protected $signature = 'wache:uhren';

    protected $description = 'Erinnert alle, deren Uhr am Abend noch läuft';

    public function handle(): int
    {
        $anzahl = Wache::laufendeUhren();

        defer()->invoke();

        $this->info($anzahl === 0 ? 'Keine Uhr läuft mehr.' : 'An '.$anzahl.' erinnert.');

        return self::SUCCESS;
    }
}
