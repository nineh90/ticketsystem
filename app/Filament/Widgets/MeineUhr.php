<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\StopptLaufendeZeiten;
use App\Models\TimeEntry;
use App\Support\LaufendeZeiten;
use App\Support\Raster;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Nur die eigene laufende Uhr.
 *
 * Das Gegenstück zu WerArbeitetGerade, das auf der Betriebsseite alle zeigt.
 * Beides nebeneinander auf einer Seite wäre doppelt; beides auf derselben
 * Seite zusammengefasst wäre die Vermischung, die dieser Umbau gerade
 * auflöst: "läuft bei mir noch etwas" und "läuft im Haus noch etwas" sind
 * zwei Fragen, und die zweite stellt man abends, die erste ständig.
 *
 * Verschwindet, wenn nichts läuft — dieselbe Begründung wie dort: eine
 * Karte, die meistens "nichts" sagt, liest nach einer Woche niemand mehr.
 */
class MeineUhr extends Widget
{
    use StopptLaufendeZeiten;

    protected string $view = 'filament.widgets.meine-uhr';

    /** Direkt unter die eigenen Zahlen. */
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = Raster::HALB;

    public static function canView(): bool
    {
        return LaufendeZeiten::eigene()->isNotEmpty();
    }

    /** @return Collection<int, TimeEntry> */
    public function getZeiten(): Collection
    {
        return LaufendeZeiten::eigene();
    }
}
