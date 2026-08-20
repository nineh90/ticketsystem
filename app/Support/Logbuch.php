<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Was hinter den Zeit-Kacheln steht.
 *
 * Auf der Brücke steht "Zeit heute", auf der Wache "Mein Logbuch diese
 * Woche" — beides bis hierher Zahlen ohne Rückseite: man sah, dass fünf
 * Stunden erfasst wurden, aber nicht, von wem, woran und wann. Genau das ist
 * die Frage, die man vor der Zahl hat, und sie war nur über die Zeitentabelle
 * jedes einzelnen Tickets zu beantworten.
 *
 * Beide Kacheln holen Summe und Auflistung deshalb aus derselben Abfrage:
 * die *Abfrage-Methoden liefern den Builder, die anderen die fertigen
 * Einträge dazu. Zwei getrennt gepflegte Bedingungen wären genau die Art von
 * Ungenauigkeit, die man einmal bemerkt — wenn die Kachel 5:20 h sagt und die
 * Liste darunter auf 4:10 h kommt, traut man danach keiner von beiden mehr.
 */
class Logbuch
{
    /**
     * Was heute im Betrieb erfasst wurde — über alle Beteiligten.
     *
     * Gefiltert über die sichtbaren Tickets und nicht über
     * TimeEntry::sichtbarFuer: dort kämen die eigenen Buchungen auch aus
     * Projekten dazu, die man sonst nicht sieht. Für "was ist heute in diese
     * Projekte geflossen" ist das die falsche Menge, und die Kachel hat schon
     * immer so gezählt.
     */
    public static function betriebHeuteAbfrage(User $nutzer): Builder
    {
        return TimeEntry::query()
            ->whereIn('ticket_id', Ticket::query()->sichtbarFuer($nutzer)->select('tickets.id'))
            ->whereDate('gestartet_am', today());
    }

    /** Die eigenen Buchungen seit Montag. */
    public static function eigeneWocheAbfrage(User $nutzer): Builder
    {
        return TimeEntry::query()
            ->where('user_id', $nutzer->getKey())
            ->where('gestartet_am', '>=', today()->startOfWeek());
    }

    /** @return Collection<int, TimeEntry> */
    public static function betriebHeute(User $nutzer): Collection
    {
        return self::eintraege(self::betriebHeuteAbfrage($nutzer));
    }

    /** @return Collection<int, TimeEntry> */
    public static function eigeneWoche(User $nutzer): Collection
    {
        return self::eintraege(self::eigeneWocheAbfrage($nutzer));
    }

    /**
     * Die Einträge zu einer dieser Abfragen, fertig zum Anzeigen.
     *
     * Neueste zuerst — anders als bei den laufenden Uhren, wo die älteste
     * oben steht, weil sie die vergessene ist. Hier liest man rückwärts:
     * "was habe ich heute gemacht" fängt bei zuletzt an.
     *
     * @return Collection<int, TimeEntry>
     */
    private static function eintraege(Builder $abfrage): Collection
    {
        return $abfrage
            ->with(['user', 'ticket.customer', 'ticket.project'])
            ->orderByDesc('gestartet_am')
            ->get();
    }
}
