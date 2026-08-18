<?php

namespace App\Support;

/**
 * Bytes so schreiben, wie man sie ausspricht.
 *
 * An einer Stelle, aus demselben Grund wie bei Dauer: die Umrechnung wird
 * inzwischen an Ticket-Anhängen und an Kundendokumenten gebraucht. Zwei
 * private Kopien derselben acht Zeilen sind kein Problem, bis eine davon
 * "1.4 MB" schreibt und die andere "1,4 MB".
 */
class Dateigroesse
{
    /** 1468006 wird als "1,4 MB" lesbarer als als "1468006". */
    public static function lesbar(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 0, ',', '.').' KB';
        }

        return number_format($bytes / 1024 / 1024, 1, ',', '.').' MB';
    }
}
