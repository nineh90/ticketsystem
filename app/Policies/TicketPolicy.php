<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->istAdmin() || $this->imEigenenProjekt($user, $ticket);
    }

    /**
     * Anlegen darf jeder — in welchem Projekt, entscheidet die Auswahlliste
     * im Formular, die über den Scope ohnehin nur zugeordnete Projekte zeigt.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->istAdmin() || $this->imEigenenProjekt($user, $ticket);
    }

    /**
     * Löschen bleibt beim Admin. Ein Ticket wegzuwerfen nimmt Kommentare und
     * Zeitbuchungen mit (cascade) — für den Alltag gibt es das Stadium
     * "Verworfen", das nichts vernichtet.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->istAdmin();
    }

    /** Dieselben zwei Wege wie Ticket::scopeSichtbarFuer. */
    private function imEigenenProjekt(User $user, Ticket $ticket): bool
    {
        if ($ticket->project->mitarbeiter()->whereKey($user->getKey())->exists()) {
            return true;
        }

        return $ticket->customer->mitarbeiter()->whereKey($user->getKey())->exists();
    }
}
