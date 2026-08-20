<?php

namespace App\Console\Commands;

use App\Enums\Erinnerung;
use App\Models\Treffen;
use App\Support\Messe;
use Illuminate\Console\Command;

/**
 * Der Planer: erinnert an Treffen, bevor sie anfangen.
 *
 * Zwei Stufen — einen Tag vorher und eine Stunde vorher (Enums\Erinnerung).
 * Läuft jede Minute, siehe routes/console.php, und braucht dafür einen
 * Dauerprozess: auf dem Server ist das der Container ticketsystem-planer
 * (deploy/docker-compose.yml). Ohne ihn ist dieser Befehl da und tut nichts,
 * und das ist der Fehler, der hier am ehesten unbemerkt bleibt — deshalb
 * meldet der Befehl jeden Versand ins Protokoll und lässt sich von Hand
 * aufrufen.
 *
 * Alles, was verhindert, dass zweimal geläutet wird, steht bewusst NICHT
 * hier, sondern in Messe::faellige(): der Stempel wird gesetzt, bevor die
 * Meldung rausgeht.
 */
class TreffenErinnern extends Command
{
    protected $signature = 'messe:erinnern';

    protected $description = 'Erinnert an Treffen, die in 24 Stunden oder in einer Stunde anfangen';

    public function handle(): int
    {
        $verschickt = 0;

        foreach (Erinnerung::cases() as $stufe) {
            foreach (Messe::faellige($stufe) as $treffen) {
                if (! $stufe->lohntSich($treffen)) {
                    continue;
                }

                Messe::erinnern($treffen, $stufe);

                $verschickt++;

                $this->line($this->zeile($treffen, $stufe));
            }
        }

        // Der Versand der Mails hängt an defer() und liefe sonst erst nach
        // der "Antwort" — die es bei einem Befehl nicht gibt. Ohne diese
        // Zeile bliebe die Glocke richtig und das Postfach leer.
        defer()->invoke();

        if ($verschickt === 0) {
            $this->info('Nichts zu melden.');
        }

        return self::SUCCESS;
    }

    private function zeile(Treffen $treffen, Erinnerung $stufe): string
    {
        return sprintf(
            '→ %s: %s (%s)',
            $stufe->anlass($treffen),
            $treffen->titel,
            $treffen->beginnt_am->format('d.m.Y H:i'),
        );
    }
}
