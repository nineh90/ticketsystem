<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Woher ein Ticket kam. */
enum Quelle: string implements HasLabel
{
    case Manuell = 'manuell';

    /** Über POST /api/v1/tickets angelegt, in der Regel durch n8n. */
    case Api = 'api';

    /** Aus einer Mail erzeugt — vorgesehen für Lerndex & Co. */
    case Email = 'email';

    /**
     * Vom Kunden selbst im Kundenbereich gemeldet.
     *
     * Der Unterschied zu "manuell" ist nicht bloß buchhalterisch: an dieser
     * Quelle hängt, dass jemand draußen auf eine Antwort wartet. Danach
     * filtert der Reiter in der Ticketliste, und danach entscheidet sich,
     * ob eine Benachrichtigung ausgelöst wird.
     */
    case Kunde = 'kunde';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manuell => 'Manuell',
            self::Api => 'Schnittstelle',
            self::Email => 'E-Mail',
            self::Kunde => 'Vom Kunden',
        };
    }
}
