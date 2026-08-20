<?php

namespace App\Console\Commands;

use App\Support\Kasse;
use Illuminate\Console\Command;

/** Montags: überfällige Rechnungen und liegende Angebote. */
class OffenePosten extends Command
{
    protected $signature = 'kasse:fristen';

    protected $description = 'Meldet überfällige Rechnungen und unbeantwortete Angebote';

    public function handle(): int
    {
        $anzahl = Kasse::fristen();

        defer()->invoke();

        $this->info($anzahl === 0 ? 'Nichts überfällig.' : 'An '.$anzahl.' gemeldet.');

        return self::SUCCESS;
    }
}
