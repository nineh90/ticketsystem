<?php

namespace App\Support;

/**
 * Vorschlag für ein Startpasswort, das man am Telefon vorlesen kann.
 *
 * Solange kein Mailversand eingerichtet ist, geht das erste Passwort durch
 * einen menschlichen Kanal — vorgelesen, in einer Nachricht getippt,
 * abgeschrieben. Eine Zufallskette aus 24 Zeichen scheitert daran zuverlässig:
 * sie wird falsch übertragen, und im zweiten Anlauf nimmt jemand "Kunde2026".
 *
 * Deshalb drei kurze Wörter und eine Zahl. Das ist kein Dauerpasswort — der
 * Kunde ändert es unter "Profil", und die Länge liegt trotzdem deutlich über
 * der Mindestanforderung.
 */
class Startpasswort
{
    /**
     * Bewusst ohne Umlaute, ohne ß und ohne Wörter, die sich beim Vorlesen
     * verwechseln lassen (Bein/Wein). Alles gut buchstabierbar.
     *
     * @var list<string>
     */
    private const WOERTER = [
        'Anker', 'Balkon', 'Birne', 'Blume', 'Brille', 'Bruecke', 'Dachs',
        'Distel', 'Falke', 'Feder', 'Fenster', 'Garten', 'Gitarre', 'Hafen',
        'Hammer', 'Insel', 'Kamin', 'Kastanie', 'Kompass', 'Krone', 'Lampe',
        'Laterne', 'Leiter', 'Mandel', 'Marmor', 'Muschel', 'Nadel', 'Nebel',
        'Olive', 'Pfeffer', 'Pinsel', 'Planke', 'Quelle', 'Rakete', 'Regen',
        'Ritter', 'Schaufel', 'Segel', 'Spiegel', 'Tanne', 'Teppich', 'Truhe',
        'Turm', 'Vogel', 'Wolke', 'Wurzel', 'Zeder', 'Zirkus',
    ];

    public static function erzeugen(): string
    {
        $woerter = [];

        // Ohne Wiederholung: "Insel-Insel-Turm" sieht nach einem Fehler aus
        // und wird beim Vorlesen zur Rückfrage.
        while (count($woerter) < 3) {
            $wort = self::WOERTER[random_int(0, count(self::WOERTER) - 1)];

            if (! in_array($wort, $woerter, strict: true)) {
                $woerter[] = $wort;
            }
        }

        return implode('-', $woerter).'-'.random_int(10, 99);
    }
}
