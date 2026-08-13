<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Priorität eines Tickets.
 *
 * Anders als die Stadien ein Enum und keine Tabelle: die vier Stufen ändern
 * sich nicht, und an ihrer Reihenfolge hängt Sortierlogik. Stadien sind
 * Arbeitsablauf und sollen konfigurierbar sein, Priorität ist es nicht.
 */
enum Prioritaet: string implements HasColor, HasLabel
{
    case Niedrig = 'niedrig';
    case Normal = 'normal';
    case Hoch = 'hoch';
    case Dringend = 'dringend';

    public function getLabel(): string
    {
        return match ($this) {
            self::Niedrig => 'Niedrig',
            self::Normal => 'Normal',
            self::Hoch => 'Hoch',
            self::Dringend => 'Dringend',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Niedrig => 'gray',
            self::Normal => 'info',
            self::Hoch => 'warning',
            self::Dringend => 'danger',
        };
    }

    /** Für "wichtigste zuerst" — höher ist dringender. */
    public function gewicht(): int
    {
        return match ($this) {
            self::Niedrig => 0,
            self::Normal => 1,
            self::Hoch => 2,
            self::Dringend => 3,
        };
    }
}
