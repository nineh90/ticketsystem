<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjektStatus: string implements HasColor, HasLabel
{
    case Aktiv = 'aktiv';
    case Pausiert = 'pausiert';
    case Abgeschlossen = 'abgeschlossen';

    /**
     * Kam nie zustande oder wurde abgebrochen — etwa das zweite Projekt
     * eines Kunden, das er doch nicht beauftragt. Anders als ein gelöschtes
     * Projekt bleiben Tickets und gebuchte Zeiten erhalten.
     */
    case Abgesagt = 'abgesagt';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aktiv => 'Aktiv',
            self::Pausiert => 'Pausiert',
            self::Abgeschlossen => 'Abgeschlossen',
            self::Abgesagt => 'Abgesagt',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aktiv => 'success',
            self::Pausiert => 'warning',
            self::Abgeschlossen => 'gray',
            self::Abgesagt => 'danger',
        };
    }

    /**
     * Ruht das Projekt — auf Eis oder abgesagt?
     *
     * Ein ruhendes Projekt verschwindet aus dem Kundenbereich, als wäre es
     * nicht freigegeben (siehe Project::scopeFuerKundenFreigegeben).
     */
    public function ruht(): bool
    {
        return in_array($this, self::ruhende(), true);
    }

    /** @return list<self> */
    public static function ruhende(): array
    {
        return [self::Pausiert, self::Abgesagt];
    }
}
