<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjektStatus: string implements HasColor, HasLabel
{
    case Aktiv = 'aktiv';
    case Pausiert = 'pausiert';
    case Abgeschlossen = 'abgeschlossen';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aktiv => 'Aktiv',
            self::Pausiert => 'Pausiert',
            self::Abgeschlossen => 'Abgeschlossen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aktiv => 'success',
            self::Pausiert => 'warning',
            self::Abgeschlossen => 'gray',
        };
    }
}
