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
        // Der Kunde sieht sein eigenes Projekt, und nur solange es
        // freigegeben ist. Dieselbe Bedingung wie in
        // Project::scopeSichtbarFuer — die beiden gehören zusammen.
        if ($user->istKunde()) {
            return $user->customer_id !== null
                && $project->customer_id === $user->customer_id
                && $project->kunden_sichtbar;
        }

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

    /**
     * Zugeordnet über das Projekt selbst ODER über dessen Kunden. Muss
     * dieselben zwei Wege kennen wie Project::scopeSichtbarFuer — sonst
     * zeigte die Liste ein Projekt an, das sich nicht öffnen lässt.
     */
    private function istZugeordnet(User $user, Project $project): bool
    {
        if ($project->mitarbeiter()->whereKey($user->getKey())->exists()) {
            return true;
        }

        return $project->customer->mitarbeiter()->whereKey($user->getKey())->exists();
    }
}
