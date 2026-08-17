<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Mit wem eine Unterhaltung geführt wird.
 *
 * Der Wert entscheidet über den Empfängerkreis und damit über alles, was in
 * diesem System heikel ist — siehe UnterhaltungPolicy. Deshalb ein Enum und
 * keine Spalte "ist_intern": ein Schalter mit zwei Zuständen lädt dazu ein,
 * ihn irgendwo zu vergessen, und "false" sähe dann aus wie eine Entscheidung.
 */
enum Unterhaltungsart: string implements HasLabel
{
    /** Zwischen uns und einem Kunden. Gehört dem Kunden, nicht einer Person. */
    case Kunde = 'kunde';

    /** Zwischen zwei von uns. Niemand sonst liest mit, auch kein Administrator. */
    case Intern = 'intern';

    public function getLabel(): string
    {
        return match ($this) {
            self::Kunde => 'Kunde',
            self::Intern => 'Intern',
        };
    }
}
