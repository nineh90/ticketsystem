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
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TimeEntry $eintrag): bool
    {
        return $user->istAdmin() || $user->is($eintrag->user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TimeEntry $eintrag): bool
    {
        return $user->istAdmin() || $user->is($eintrag->user);
    }

    public function delete(User $user, TimeEntry $eintrag): bool
    {
        return $user->istAdmin() || $user->is($eintrag->user);
    }
}
