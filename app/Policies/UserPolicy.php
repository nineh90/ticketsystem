<?php

namespace App\Policies;

use App\Models\User;

/**
 * Nutzerverwaltung ist Admin-Sache — hier werden Rollen und Freigaben
 * vergeben, das ist die Stelle, an der jemand sich selbst zum Admin machen
 * könnte.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->istAdmin();
    }

    public function view(User $user, User $ziel): bool
    {
        return $user->istAdmin() || $user->is($ziel);
    }

    public function create(User $user): bool
    {
        return $user->istAdmin();
    }

    public function update(User $user, User $ziel): bool
    {
        return $user->istAdmin();
    }

    /**
     * Niemand löscht sich selbst.
     *
     * Nicht aus Höflichkeit: wäre es der einzige Admin, gäbe es danach
     * niemanden mehr, der Freigaben erteilen kann — und weil panel_zugang
     * standardmäßig false ist, käme man auch über einen neu angelegten
     * Account nicht mehr hinein. Das System wäre zugesperrt.
     */
    public function delete(User $user, User $ziel): bool
    {
        return $user->istAdmin() && ! $user->is($ziel);
    }
}
