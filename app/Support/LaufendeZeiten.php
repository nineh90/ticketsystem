<?php

namespace App\Support;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Wer gerade an der Uhr hängt.
 *
 * Bis hierher war eine laufende Zeitbuchung nur an dem einen Ticket zu sehen,
 * an dem sie gestartet wurde. Wer abends das Fenster schließt, ohne noch
 * einmal dorthin zu klicken, lässt die Uhr über Nacht laufen und findet am
 * nächsten Morgen eine Buchung über vierzehn Stunden — die dann von Hand
 * korrigiert werden muss und die Abrechnung verfälscht, wenn es niemandem
 * auffällt.
 *
 * Deshalb laufen die laufenden Uhren an zwei Stellen zusammen: über der
 * Zeitentabelle jedes Tickets (dort, wo man ohnehin steht, wenn man mit Zeit
 * zu tun hat) und als eigene Karte auf dem Dashboard. Beide holen ihre Daten
 * hier, damit sie nicht auseinanderlaufen.
 */
class LaufendeZeiten
{
    /**
     * Alle laufenden Buchungen, die dieser Nutzer sehen darf.
     *
     * Älteste zuerst: die Uhr, die am längsten läuft, ist die, die am
     * ehesten vergessen wurde.
     *
     * @return Collection<int, TimeEntry>
     */
    public static function fuer(?User $nutzer = null): Collection
    {
        $nutzer ??= auth()->user();

        if ($nutzer === null) {
            return collect();
        }

        return TimeEntry::query()
            ->laufend()
            ->sichtbarFuer($nutzer)
            ->with(['user', 'ticket.customer', 'ticket.project'])
            ->orderBy('gestartet_am')
            ->get();
    }

    /** Läuft überhaupt etwas? Ohne die Einträge dafür zu laden. */
    public static function gibtEs(?User $nutzer = null): bool
    {
        $nutzer ??= auth()->user();

        if ($nutzer === null) {
            return false;
        }

        return TimeEntry::query()
            ->laufend()
            ->sichtbarFuer($nutzer)
            ->exists();
    }
}
