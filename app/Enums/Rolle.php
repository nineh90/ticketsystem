<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Rollen im Ticketsystem.
 *
 * Bewusst ein Enum und kein Rechte-Paket wie spatie/laravel-permission: bei
 * dieser Zahl von Rollen sind Enum + Policies kürzer und lesbarer. Sollten
 * später feingranulare Rechte nötig werden ("darf Zeiten sehen, aber nicht
 * löschen"), ist der Wechsel ein isolierter Umbau der Policies — die
 * Aufrufstellen fragen bereits heute nur über Policies, nie direkt die Rolle.
 */
enum Rolle: string implements HasLabel
{
    /** Sieht und darf alles, verwaltet Nutzer, Kunden, Projekte, Stadien. */
    case Admin = 'admin';

    /** Sieht nur die Projekte, denen er zugeordnet ist. */
    case Mitarbeiter = 'mitarbeiter';

    /**
     * Vorbereitet für den späteren Kundenbereich (eigenes Filament-Panel).
     * In v1 wird diese Rolle nicht vergeben.
     */
    case Kunde = 'kunde';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Mitarbeiter => 'Mitarbeiter',
            self::Kunde => 'Kunde',
        };
    }
}
