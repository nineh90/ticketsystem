<?php

namespace App\Filament\Widgets;

use App\Models\Unterhaltung;
use App\Support\Raster;
use App\Support\Unterhaltungen;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Unterhaltungen, in denen etwas Ungelesenes steht.
 *
 * Die Glocke meldet jede Nachricht einmal; diese Karte zeigt, was davon noch
 * unbeantwortet ist. Derselbe Gedanke wie bei VonKunden gegenüber der Glocke:
 * eine weggeklickte Benachrichtigung darf eine offene Frage nicht aus der
 * Welt schaffen.
 *
 * Verschwindet vollständig, sobald alles gelesen ist.
 */
class MeineNachrichten extends Widget
{
    protected string $view = 'filament.widgets.meine-nachrichten';

    /** Neben der eigenen Uhr, über der Ticketliste. */
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = Raster::HALB;

    public static function canView(): bool
    {
        return Unterhaltungen::ungelesen() > 0;
    }

    /**
     * Nur die Fäden mit Ungelesenem — und zwar mit der Anzahl daneben.
     *
     * @return Collection<int, array{unterhaltung: Unterhaltung, titel: string, ungelesen: int}>
     */
    public function getOffene(): Collection
    {
        $nutzer = auth()->user();

        if ($nutzer === null) {
            return collect();
        }

        return Unterhaltungen::fuer($nutzer)
            ->map(fn (Unterhaltung $unterhaltung) => [
                'unterhaltung' => $unterhaltung,
                'titel' => $unterhaltung->titelFuer($nutzer),
                'ungelesen' => $unterhaltung->ungeleseneFuer($nutzer),
            ])
            ->filter(fn (array $zeile) => $zeile['ungelesen'] > 0)
            ->values();
    }
}
