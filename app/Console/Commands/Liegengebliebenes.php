<?php

namespace App\Console\Commands;

use App\Support\Wache;
use Illuminate\Console\Command;

/** Montags: was ohne Bewegung liegt und was niemandem gehört. */
class Liegengebliebenes extends Command
{
    protected $signature = 'wache:liegengebliebenes';

    protected $description = 'Meldet ruhende Tickets an ihre Zuständigen und unzugeteilte an die Administratoren';

    public function handle(): int
    {
        $anzahl = Wache::liegengebliebenes();

        defer()->invoke();

        $this->info($anzahl === 0 ? 'Nichts liegt.' : 'An '.$anzahl.' gemeldet.');

        return self::SUCCESS;
    }
}
