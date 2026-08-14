<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Hält fest, wann sich jemand zuletzt angemeldet hat.
 *
 * Gebraucht wird das vor allem bei den Kundenzugängen: nachdem man ein
 * Startpasswort weitergegeben hat, ist die erste Frage immer, ob es
 * angekommen ist. Ohne diese Angabe schickt man die Zugangsdaten zum dritten
 * Mal hinterher, obwohl der Kunde längst drin war und nur nichts zu melden
 * hatte.
 */
class AnmeldungMerken
{
    public function handle(Login $ereignis): void
    {
        $nutzer = $ereignis->user;

        if (! $nutzer instanceof User) {
            return;
        }

        // Bewusst über den Query Builder statt über save(): ein save() zöge
        // updated_at mit und ließe jede Anmeldung wie eine Änderung am Konto
        // aussehen — im Verlauf und in jeder Liste, die nach "zuletzt
        // geändert" sortiert. Dasselbe Vorgehen wie bei DashboardBesuch.
        User::query()
            ->whereKey($nutzer->getKey())
            ->update(['letzte_anmeldung_at' => now()]);
    }
}
