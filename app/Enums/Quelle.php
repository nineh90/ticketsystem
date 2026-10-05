<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Woher ein Ticket kam. */
enum Quelle: string implements HasLabel
{
    case Manuell = 'manuell';

    /** Über POST /api/v1/tickets angelegt, in der Regel durch n8n. */
    case Api = 'api';

    /**
     * Von der Website nils-digital.de eingeliefert: Kontaktformular und
     * Projektfragebogen.
     *
     * Technisch derselbe Weg wie "api", aber eine andere Lage: hier schreibt
     * jemand, der noch kein Kunde ist und auf eine Antwort wartet. Gesetzt
     * wird die Quelle anhand des Tokens (siehe Middleware\ApiToken), nicht
     * anhand eines Feldes im Aufruf — sonst könnte sich jeder Aufrufer als
     * Website ausgeben.
     */
    case Website = 'website';

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
            self::Website => 'Website',
            self::Email => 'E-Mail',
            self::Kunde => 'Vom Kunden',
        };
    }
}
