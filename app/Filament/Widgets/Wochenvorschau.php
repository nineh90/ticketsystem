<?php

namespace App\Filament\Widgets;

use App\Support\Termin;
use App\Support\Wochenplan;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Die nächsten sieben Tage — auf der Brücke.
 *
 * Steht bewusst dort und nicht auf der Wache: die Wache beantwortet, was an
 * mir hängt (dafür gibt es MeineTreffen), die Brücke den Blick aufs Ganze.
 * Ein Termin, den ein Kollege hat, gehört genau dorthin — sonst erfährt man
 * von der Abnahme am Donnerstag erst am Donnerstag.
 *
 * Was darin steht, sammelt Wochenplan aus vier Quellen ein. Jede geht durch
 * ihr eigenes sichtbarFuer; ein Mitarbeiter sieht hier also die Termine
 * seiner Kunden und keine fremden.
 */
class Wochenvorschau extends Widget
{
    protected string $view = 'filament.widgets.wochenvorschau';

    /** Ganz oben: was diese Woche liegt, entscheidet, was man heute tut. */
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    /** @var Collection<string, Collection<int, Termin>>|null */
    private ?Collection $geladen = null;

    /** @return Collection<string, Collection<int, Termin>> */
    public function getTage(): Collection
    {
        if ($this->geladen !== null) {
            return $this->geladen;
        }

        $nutzer = auth()->user();

        return $this->geladen = $nutzer === null
            ? collect()
            : Wochenplan::jeTag($nutzer);
    }

    public function istSichtbar(): bool
    {
        return $this->getTage()->isNotEmpty();
    }

    /**
     * Die Karte steht auch dann da, wenn nichts ansteht.
     *
     * Anders als bei der Messe im Kundenbereich, und der Unterschied ist der
     * Zweck: dort ist die Karte eine Einladung, hier ist sie ein Arbeitsmittel.
     * Wer morgens auf die Brücke kommt, will die Antwort "diese Woche liegt
     * nichts an" ausdrücklich lesen — sonst weiß er nicht, ob nichts ansteht
     * oder ob die Vorschau nur nicht geladen hat.
     */
    public static function canView(): bool
    {
        return auth()->user()?->istKunde() === false;
    }
}
