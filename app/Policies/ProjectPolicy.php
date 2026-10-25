<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
        // freigegeben ist und nicht ruht. Dieselbe Bedingung wie in
        // Project::scopeSichtbarFuer — die beiden gehören zusammen.
        if ($user->istKunde()) {
            return $user->customer_id !== null
                && $project->customer_id === $user->customer_id
                && $project->istFuerKundenFreigegeben();
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

    /**
     * Löschen nur, solange das Projekt leer ist.
     *
     * Tickets hängen mit cascadeOnDelete am Projekt — mit dem Projekt
     * verschwänden also auch alle Tickets und die darauf gebuchten Zeiten,
     * und damit Stunden, die womöglich schon abgerechnet sind. Ein Projekt
     * mit Geschichte wird stattdessen auf "Abgesagt" gesetzt.
     */
    public function delete(User $user, Project $project): Response
    {
        if (! $user->istAdmin()) {
            return Response::deny();
        }

        return $project->tickets()->exists()
            ? Response::deny('Hat schon Tickets — stattdessen auf „Abgesagt“ oder „Pausiert“ setzen.')
            : Response::allow();
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
