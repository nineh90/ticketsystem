<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Wer darf was mit Projekten.
 *
 * Die Policies sind die einzige Stelle, an der Rollen ausgewertet werden.
 * Alle Aufrufer fragen über sie — nie direkt $user->rolle. Damit bleibt der
 * Wechsel auf ein feingranulares Rechtesystem später ein Umbau genau dieser
 * Klassen und nicht des halben Projekts.
 *
 * Ergänzend gilt: die Sichtbarkeit in Listen regeln die Query-Scopes
 * (Project::scopeSichtbarFuer). Policies verhindern den Direktaufruf, Scopes
 * verhindern das Auftauchen in Listen und Zählern. Beides wird gebraucht —
 * eine Policy allein ließe fremde Datensätze in Tabellen und Statistiken
 * stehen.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->istAdmin() || $this->istZugeordnet($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->istAdmin();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->istAdmin();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->istAdmin();
    }

    private function istZugeordnet(User $user, Project $project): bool
    {
        return $project->mitarbeiter()->whereKey($user->getKey())->exists();
    }
}
