<?php

namespace App\Support;

use App\Models\Treffen;
use Carbon\Carbon;

/**
 * Erzeugt den Kalendereintrag zu einem Treffen (.ics).
 *
 * Selbst geschrieben und kein Paket: eine einzelne VEVENT ist ein Dutzend
 * Zeilen, und jede Abhängigkeit hier wäre eine, die man bei jedem
 * Framework-Sprung mitziehen müsste.
 *
 * Zwei Dinge daran sind nicht offensichtlich und beide sorgen dafür, dass
 * der Eintrag in fremden Kalendern richtig ankommt:
 *
 * 1. **Zeiten gehen als UTC hinaus** (Suffix Z). Die Anwendung rechnet in
 *    Ortszeit (siehe die Zeitzonen-Migration vom 13.08.); ein Kalender ohne
 *    Zeitzonenangabe interpretiert die Zeit dagegen als die des Lesers. Ein
 *    Kunde in Wien bekäme den Termin sonst eine Stunde verschoben.
 *
 * 2. **Die UID bleibt über Änderungen gleich**, die Sequenznummer wächst.
 *    Genau daran erkennt ein Kalenderprogramm, dass ein zweiter Eintrag
 *    derselbe Termin ist — sonst steht nach dem Verschieben beides im
 *    Kalender und der Kunde erscheint zur alten Zeit.
 */
class Kalender
{
    public static function fuer(Treffen $treffen): string
    {
        $schiff = (string) config('kontakt.schiff');

        $titel = $treffen->titel.' · '.$schiff;

        $beschreibung = collect([
            $treffen->notiz,
            $treffen->url ? 'An Bord gehen: '.$treffen->url : null,
        ])->filter()->join("\n\n");

        // Die Sequenz zählt Änderungen. updated_at taugt dafür nicht direkt
        // (die Zahl muss wachsen und ganzzahlig sein), der Zeitstempel als
        // Sekunden schon.
        $sequenz = (int) ($treffen->updated_at?->timestamp ?? 0);

        $zeilen = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Nils-Digital//ND-Deck//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:treffen-'.$treffen->getKey().'@nils-digital.de',
            'SEQUENCE:'.$sequenz,
            'DTSTAMP:'.self::zeit(now()),
            'DTSTART:'.self::zeit($treffen->beginnt_am),
            'DTEND:'.self::zeit($treffen->endetAm()),
            'SUMMARY:'.self::maskieren($titel),
            // Ein abgesagtes Treffen geht als solches hinaus. Das räumt es
            // im Kalender des Kunden weg, statt ihn dort auf einen Termin
            // warten zu lassen, den es nicht mehr gibt.
            'STATUS:'.($treffen->istAbgesagt() ? 'CANCELLED' : 'CONFIRMED'),
        ];

        if (filled($beschreibung)) {
            $zeilen[] = 'DESCRIPTION:'.self::maskieren($beschreibung);
        }

        if (filled($treffen->url)) {
            // LOCATION und URL beide: Kalenderprogramme zeigen mal das eine,
            // mal das andere an, und der Link ist der ganze Zweck.
            $zeilen[] = 'LOCATION:'.self::maskieren($treffen->url);
            $zeilen[] = 'URL:'.self::maskieren($treffen->url);
        }

        $zeilen[] = 'END:VEVENT';
        $zeilen[] = 'END:VCALENDAR';

        // CRLF, nicht LF — der Standard schreibt es vor, und Outlook nimmt
        // eine Datei mit reinen Zeilenumbrüchen nicht an.
        return implode("\r\n", array_map(self::umbrechen(...), $zeilen))."\r\n";
    }

    /** Der Dateiname, unter dem der Eintrag beim Kunden landet. */
    public static function dateiname(Treffen $treffen): string
    {
        return 'treffen-'.$treffen->beginnt_am->format('Y-m-d-Hi').'.ics';
    }

    /** Ortszeit → UTC in der Schreibweise, die der Standard verlangt. */
    private static function zeit(\DateTimeInterface $zeitpunkt): string
    {
        return Carbon::instance($zeitpunkt)->utc()->format('Ymd\THis\Z');
    }

    /**
     * Sonderzeichen, die im Format eine Bedeutung haben.
     *
     * Der Backslash muss zuerst — sonst verdoppelt der nächste Schritt die
     * Schrägstriche, die dieser gerade erst gesetzt hat.
     */
    private static function maskieren(string $text): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
            $text,
        );
    }

    /**
     * Zeilen über 75 Oktetts werden umgebrochen und mit einem Leerzeichen
     * fortgesetzt. Ohne das schneiden strenge Kalenderprogramme lange Titel
     * einfach ab.
     *
     * Gezählt wird in Bytes, umgebrochen aber an Zeichengrenzen: ein in der
     * Mitte zerteiltes „ü" ergäbe eine Datei, die gar nicht mehr lesbar ist.
     */
    private static function umbrechen(string $zeile): string
    {
        if (strlen($zeile) <= 75) {
            return $zeile;
        }

        $teile = [];
        $rest = $zeile;
        $grenze = 75;

        while (strlen($rest) > $grenze) {
            $stueck = mb_strcut($rest, 0, $grenze);
            $teile[] = $stueck;
            $rest = substr($rest, strlen($stueck));

            // Folgezeilen beginnen mit einem Leerzeichen, das nicht zum
            // Inhalt gehört — es bleibt also ein Oktett weniger für Text.
            $grenze = 74;
        }

        if ($rest !== '') {
            $teile[] = $rest;
        }

        return implode("\r\n ", $teile);
    }
}
