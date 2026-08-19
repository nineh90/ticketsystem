<?php

namespace App\Filament\Widgets;

use App\Models\Treffen;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Meine nächsten Treffen — auf der Wache.
 *
 * Zeigt ausschließlich die, bei denen ich selbst dabei bin, und nicht alle
 * Termine des Betriebs. Das ist die Trennlinie der beiden Einstiegsseiten:
 * "Meine Wache" beantwortet, was an mir hängt. Ein Termin, zu dem ich nicht
 * gehöre, hängt nicht an mir.
 *
 * Wie beim Kunden erscheint die Karte nur, wenn etwas ansteht.
 */
class MeineTreffen extends Widget
{
    protected string $view = 'filament.widgets.meine-treffen';

    /** Über den Tickets: ein Termin hat eine Uhrzeit, eine Aufgabe nicht. */
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /** @var Collection<int, Treffen>|null */
    private ?Collection $geladen = null;

    /** @return Collection<int, Treffen> */
    public function getTreffen(): Collection
    {
        return $this->geladen ??= self::meine();
    }

    /**
     * Dieselbe Abfrage ohne Objekt — canView() ist statisch.
     *
     * Abgesagte bleiben draußen: intern ist eine Absage erledigt, sobald man
     * sie gelesen hat. Beim Kunden steht sie durchgestrichen weiter da, weil
     * er sonst nur ein verschwundenes Treffen sähe.
     *
     * @return Collection<int, Treffen>
     */
    private static function meine(): Collection
    {
        $nutzer = auth()->user();

        if ($nutzer === null || $nutzer->istKunde()) {
            return collect();
        }

        return Treffen::query()
            ->whereHas('crew', fn ($q) => $q->whereKey($nutzer->getKey()))
            ->bevorstehend()
            ->nichtAbgesagt()
            ->alsNaechstes()
            ->with('customer')
            ->limit(5)
            ->get();
    }

    public function istSichtbar(): bool
    {
        return $this->getTreffen()->isNotEmpty();
    }

    public static function canView(): bool
    {
        return self::meine()->isNotEmpty();
    }
}
