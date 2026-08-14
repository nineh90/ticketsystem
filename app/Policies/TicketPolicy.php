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
        if ($user->istKunde()) {
            return $this->imEigenenFreigegebenenProjekt($user, $ticket);
        }

        return $user->istAdmin() || $this->imEigenenProjekt($user, $ticket);
    }

    /**
     * Anlegen darf jeder — in welchem Projekt, entscheidet die Auswahlliste
     * im Formular, die über den Scope ohnehin nur zugeordnete Projekte zeigt.
     * Bei Kunden wird das Projekt beim Speichern zusätzlich nachgeprüft
     * (CreateAnliegen), weil eine Auswahlliste keine Prüfung ist.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Ändern ist unsere Sache.
     *
     * Ein Kunde legt an, liest und antwortet — mehr nicht. Im Kundenbereich
     * gibt es dafür schon keine Oberfläche; diese Zeile ist die Absicherung
     * dahinter, denn eine fehlende Schaltfläche ist kein Zugriffsschutz.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->istKunde()) {
            return false;
        }

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

    /**
     * Dieselbe Bedingung wie Ticket::scopeSichtbarFuer für Kunden.
     *
     * Muss mit ihr übereinstimmen: der Scope hält fremde Tickets aus Listen
     * heraus, diese Prüfung wehrt den Direktaufruf einer Adresse ab. Läuft
     * eine der beiden der anderen davon, ist entweder etwas sichtbar, das
     * sich nicht öffnen lässt — oder, schlimmer, umgekehrt.
     */
    private function imEigenenFreigegebenenProjekt(User $user, Ticket $ticket): bool
    {
        $projekt = $ticket->project;

        return $projekt !== null
            && $user->customer_id !== null
            && $projekt->customer_id === $user->customer_id
            && $projekt->kunden_sichtbar;
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
