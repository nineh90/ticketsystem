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
            self::Interessent => 'Interessent',
            self::Aktiv => 'In Betreuung',
            self::Ruhend => 'Ruhend',
            self::Beendet => 'Beendet',
        };
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
