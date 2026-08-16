<?php

namespace App\Support;

/**
 * Minuten so schreiben, wie man sie ausspricht.
 *
 * An einer Stelle, weil dieselbe Umrechnung inzwischen in beiden Überblicken,
 * in der Zeitentabelle und bei den laufenden Uhren gebraucht wird. Mehrere
 * private Kopien derselben drei Zeilen sind kein Problem, bis eine davon
 * "1:5 h" schreibt.
 *
 * Nicht hierher gehört die Fassung in Ereignisstrom: die schreibt unter einer
 * Stunde "45 min" statt "0:45 h", weil im Ereignisstrom die kurzen Buchungen
 * überwiegen. Das ist ein anderer Zweck, keine Abweichung.
 */
class Dauer
{
    /** 135 Minuten werden als "2:15 h" lesbarer als als "135". */
    public static function alsStunden(int $minuten): string
    {
        return intdiv($minuten, 60).':'.str_pad((string) ($minuten % 60), 2, '0', STR_PAD_LEFT).' h';
    }
}
