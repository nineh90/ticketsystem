<?php

namespace App\Filament\Kunde\Widgets;

use App\Models\Treffen;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Die Messe auf der Übersicht des Kunden — das nächste Treffen an Bord.
 *
 * Die Karte erscheint nur, wenn tatsächlich etwas ansteht. Ein Kasten, in dem
 * elf Monate lang "keine Termine" steht, ist derselbe Fehler wie ein leerer
 * Menüpunkt: man gewöhnt sich an, ihn zu übergehen, und übersieht ihn dann
 * auch, wenn zum ersten Mal etwas darin steht.
 */
class Messe extends Widget
{
    protected string $view = 'filament.kunde.widgets.messe';

    /**
     * Direkt unter der Begrüßung.
     *
     * Ein Termin ist zeitkritisch — er gehört über die Anliegen-Zahlen, die
     * auch morgen noch dieselben sind.
     */
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * Einmal geholt, mehrfach gebraucht: mount(), istSichtbar() und die
     * Ansicht fragen alle dasselbe. Ohne das wäre es dieselbe Abfrage
     * dreimal je Seitenaufruf.
     *
     * @var Collection<int, Treffen>|null
     */
    private ?Collection $geladen = null;

    /**
     * Was bevorsteht — höchstens drei.
     *
     * Mehr sind auf einer Übersicht kein Gewinn: wer vier Termine mit uns
     * hat, sucht nicht mehr den nächsten, sondern einen bestimmten, und
     * dafür ist die Karte nicht da.
     *
     * @return Collection<int, Treffen>
     */
    public function getTreffen(): Collection
    {
        return $this->geladen ??= self::bevorstehende();
    }

    /**
     * Dieselbe Abfrage, aber ohne Objekt — canView() ist statisch und
     * entscheidet, ob es das Widget überhaupt gibt.
     *
     * @return Collection<int, Treffen>
     */
    private static function bevorstehende(): Collection
    {
        $nutzer = auth()->user();

        if ($nutzer === null) {
            return collect();
        }

        return Treffen::query()
            ->sichtbarFuer($nutzer)
            ->bevorstehend()
            ->alsNaechstes()
            ->with('project')
            ->limit(3)
            ->get();
    }

    /**
     * Sobald der Kunde die Übersicht öffnet, hat er die Termine gesehen —
     * die Meldungen dazu zählen dann nicht mehr an der Glocke mit.
     *
     * Dieselbe Regel wie beim geöffneten Ticket und beim geöffneten Verlauf:
     * gelesen ist, was jemand vor sich hatte, nicht was er zusätzlich
     * weggeklickt hat. Es gibt bewusst keine eigene Seite je Treffen, die
     * man dafür aufrufen müsste — ein Termin ist eine Zeile, keine Akte.
     */
    public function mount(): void
    {
        $nutzer = auth()->user();

        foreach ($this->getTreffen() as $treffen) {
            Benachrichtigung::gesehen($nutzer, Herkunft::treffen($treffen));
        }
    }

    /**
     * Der Name des Schiffs, unter dem die Karte steht.
     *
     * Aus der Konfiguration und nicht hier eingetippt: er steht auch im
     * Kalendereintrag und in der Meldung, und drei Stellen wären zwei zu
     * viel.
     */
    public function getSchiff(): string
    {
        return (string) config('kontakt.schiff');
    }

    public function istSichtbar(): bool
    {
        return $this->getTreffen()->isNotEmpty();
    }

    /**
     * Steht nichts an, gibt es die Karte nicht.
     *
     * Hier und nicht nur in der Ansicht: was canView() verneint, rendert
     * Filament gar nicht erst — sonst bliebe auf der Übersicht eine leere
     * Zeile im Raster stehen. Die Ansicht prüft trotzdem ein zweites Mal,
     * weil ein Widget auch direkt aufgerufen werden kann.
     */
    public static function canView(): bool
    {
        if (auth()->user()?->istKunde() !== true) {
            return false;
        }

        return self::bevorstehende()->isNotEmpty();
    }
}
