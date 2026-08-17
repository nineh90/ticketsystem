<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\Unterhaltung;

/**
 * Worauf sich eine Benachrichtigung bezieht.
 *
 * Eine Meldung an der Glocke ist bis hierher ein Zettel gewesen: sie sagt,
 * dass etwas passiert ist, weiß aber nicht wozu. Damit ließ sie sich nur auf
 * einem einzigen Weg loswerden — durch Anklicken in der Glocke selbst. Wer
 * die Antwort schon im Ticket gelesen hatte, trug die Zahl trotzdem weiter
 * vor sich her, und nach der dritten Woche sagt eine Zahl, die immer da ist,
 * gar nichts mehr.
 *
 * Diese Klasse gibt jeder Meldung eine Herkunft mit, die in ihren Daten
 * landet ("ticket:42"). Öffnet jemand später genau diese Sache, gelten alle
 * Meldungen dazu als gelesen — siehe Benachrichtigung::gesehen().
 *
 * Bewusst eine Zeichenkette und kein zweiter Fremdschlüssel an der
 * notifications-Tabelle: die Tabelle gehört Laravel, Filament liest sie
 * unverändert, und eine zusätzliche Spalte wäre eine Änderung an fremdem
 * Bestand. Die Herkunft passt in die data-Spalte, die ohnehin uns gehört.
 */
class Herkunft
{
    public static function ticket(Ticket|int $ticket): string
    {
        return 'ticket:'.($ticket instanceof Ticket ? $ticket->getKey() : $ticket);
    }

    public static function unterhaltung(Unterhaltung|int $unterhaltung): string
    {
        return 'unterhaltung:'.($unterhaltung instanceof Unterhaltung ? $unterhaltung->getKey() : $unterhaltung);
    }

    /**
     * Für alles, was einen Kunden betrifft, aber kein Ticket ist — heute die
     * Meldung über geänderte Stammdaten. Gelesen ist sie, sobald jemand die
     * Kundenakte öffnet.
     */
    public static function kunde(int $customerId): string
    {
        return 'kunde:'.$customerId;
    }
}
