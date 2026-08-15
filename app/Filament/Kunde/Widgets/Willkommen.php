<?php

namespace App\Filament\Kunde\Widgets;

use App\Models\Customer;
use Filament\Widgets\Widget;

/**
 * Der Kopf der Kundenübersicht: Logo, Begrüßung, Firmenname.
 *
 * Das Logo erscheint sonst nur als Kreis von zwei Zentimetern im
 * Benutzermenü — also an der Stelle, an der es niemand ansieht. Hier steht
 * es dort, wo der Kunde ohnehin hinschaut, und macht aus einer
 * Verwaltungsoberfläche seinen Bereich.
 *
 * Ohne Logo bleibt die Zeile trotzdem sinnvoll: dann steht der Firmenname
 * allein da, so wie vorher die Unterzeile der Seite. Ein Platzhalterbild
 * wäre schlechter als keines.
 */
class Willkommen extends Widget
{
    protected string $view = 'filament.kunde.widgets.willkommen';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function getKunde(): ?Customer
    {
        return auth()->user()?->customer;
    }

    /** Nur der Vorname — "Guten Tag, Frau Belmar" wäre steif, der volle Name lang. */
    public function getVorname(): string
    {
        return str(auth()->user()->name)->before(' ')->toString();
    }
}
