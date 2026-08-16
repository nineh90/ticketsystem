<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\StopptLaufendeZeiten;
use App\Models\TimeEntry;
use App\Support\LaufendeZeiten;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Wer gerade an einem Ticket sitzt.
 *
 * Beantwortet zwei Fragen auf einmal, und beide waren vorher nirgends zu
 * sehen: woran arbeitet das Team gerade — und läuft irgendwo noch eine Uhr,
 * die jemand vergessen hat. Deshalb steht die Karte weit oben und nicht unten
 * bei den Auswertungen: eine vergessene Uhr, die man erst beim Scrollen
 * findet, findet man erst am nächsten Tag.
 *
 * Wie bei VonKunden verschwindet die Karte vollständig, wenn nichts läuft.
 * Eine Karte, die meistens "niemand arbeitet gerade" sagt, liest nach einer
 * Woche niemand mehr — und dann fällt auch nicht auf, wenn doch etwas
 * darinsteht.
 */
class WerArbeitetGerade extends Widget
{
    use StopptLaufendeZeiten;

    protected string $view = 'filament.widgets.wer-arbeitet-gerade';

    /** Direkt hinter den Kundenanliegen, vor den eigenen Zahlen. */
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return LaufendeZeiten::gibtEs();
    }

    /** @return Collection<int, TimeEntry> */
    public function getZeiten(): Collection
    {
        return LaufendeZeiten::fuer();
    }
}
