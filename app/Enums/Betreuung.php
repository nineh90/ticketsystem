<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Der Stand einer Kundenbeziehung.
 *
 * Neben customers.aktiv und nicht an dessen Stelle: "aktiv" ist ein Schalter
 * ("taucht in Auswahllisten auf"), das hier ist ein Zustand. Ein Interessent
 * soll auswählbar sein — man legt ihm ja gerade ein Angebot an —, ist aber
 * kein Kunde. Ein beendeter Kunde ist umgekehrt beides nicht mehr.
 *
 * Bewusst rein intern: im Kundenbereich steht nirgends, in welche Schublade
 * jemand gehört.
 */
enum Betreuung: string implements HasColor, HasLabel
{
    case Interessent = 'interessent';
    case Aktiv = 'aktiv';
    case Ruhend = 'ruhend';
    case Beendet = 'beendet';

    public function getLabel(): string
    {
        return match ($this) {
            self::Interessent => 'Am Kai',
            self::Aktiv => 'An Bord',
            self::Ruhend => 'Vor Anker',
            self::Beendet => 'Von Bord',
        };
    }

    /**
     * Was der Stand bedeutet — als Kurzhinweis am Formular und als Tooltip
     * an der Spalte.
     *
     * Ohne sie wäre "Vor Anker" ein hübsches Wort, bei dem jeder Neue raten
     * müsste, ob es pausiert oder beendet heißt. Ein Bild, das man erklären
     * muss, ist nur dann brauchbar, wenn die Erklärung danebensteht.
     */
    public function beschreibung(): string
    {
        return match ($this) {
            self::Interessent => 'Interessent — der Kontakt steht, an Bord ist er noch nicht.',
            self::Aktiv => 'In Betreuung. Fährt mit.',
            self::Ruhend => 'Pausiert. Die Verbindung besteht, sie bewegt sich gerade nur nicht.',
            self::Beendet => 'Beendet. Von Bord gegangen.',
        };
    }

    /**
     * Alle vier in einer Zeile, als Legende unter der Auswahl.
     *
     * Die alten Wörter stehen bewusst als Übersetzung dabei: wer "Ruhend"
     * im Kopf hat, findet damit sofort wieder, was er sucht.
     */
    public static function legende(): string
    {
        return collect(self::cases())
            ->map(fn (self $stand) => $stand->getLabel().' = '.str($stand->beschreibung())->before(' —')->before('.')->toString())
            ->join(' · ');
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Interessent => 'info',
            self::Aktiv => 'success',
            self::Ruhend => 'warning',
            self::Beendet => 'gray',
        };
    }
}
