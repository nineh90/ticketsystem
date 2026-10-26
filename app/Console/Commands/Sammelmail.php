<?php

namespace App\Console\Commands;

use App\Support\Sammelmail as Postausgang;
use Illuminate\Console\Command;

/** Leert den Postausgang einer Seite: eine Mail je Empfänger. Siehe Support\Sammelmail. */
class Sammelmail extends Command
{
    protected $signature = 'post:sammelmail {seite : intern oder kunden}';

    protected $description = 'Schickt die gesammelten Meldungen als eine Mail je Empfänger';

    public function handle(): int
    {
        $seite = $this->argument('seite');

        if (! in_array($seite, ['intern', 'kunden'], true)) {
            $this->error('Seite ist "intern" oder "kunden".');

            return self::INVALID;
        }

        $anzahl = Postausgang::leeren(kunden: $seite === 'kunden');

        $this->info($anzahl === 0 ? 'Nichts zu verschicken.' : 'An '.$anzahl.' verschickt.');

        return self::SUCCESS;
    }
}
