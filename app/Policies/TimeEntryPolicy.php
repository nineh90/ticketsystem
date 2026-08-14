<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

/**
 * Zeitbuchungen gehören der Person, die sie erfasst hat.
 *
 * Fremde Buchungen zu ändern ist Admin-Sache — sonst könnte jeder die
 * Stundenzahl eines Kollegen korrigieren, und die Zahlen taugen als
 * Abrechnungsgrundlage nichts mehr.
 */
class TimeEntryPolicy
{
    /**
     * Für Kunden gibt es hier nichts — in keiner Richtung.
     *
     * Erfasste Zeiten sind unsere Kalkulation. Der Kundenbereich zeigt sie
     * nirgends; diese Zeilen sorgen dafür, dass das auch dann so bleibt,
     * wenn dort einmal versehentlich eine Spalte oder ein Zähler landet.
     */
    public function viewAny(User $user): bool
    {
        return ! $user->istKunde();
    }

    public function view(User $user, TimeEntry $eintrag): bool
    {
        if ($user->istKunde()) {
            return false;
        }

        return $user->istAdmin() || $user->is($eintrag->user);
    }

    public function create(User $user): bool
    {
        return ! $user->istKunde();
    }

    public function update(User $user, TimeEntry $eintrag): bool
    {
        if ($user->istKunde()) {
            return false;
        }

        return $user->istAdmin() || $user->is($eintrag->user);
    }

    public function delete(User $user, TimeEntry $eintrag): bool
    {
        if ($user->istKunde()) {
            return false;
        }

        return $user->istAdmin() || $user->is($eintrag->user);
    }
}
