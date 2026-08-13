<?php

namespace App\Filament\Widgets;

use App\Support\DashboardBesuch;
use App\Support\Ereignis;
use App\Support\Ereignisstrom;
use App\Support\Sichtbarkeit;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Der Ereignisstrom auf dem Dashboard.
 *
 * Bis hierher war jede Änderung nur im jeweiligen Ticket zu sehen — man
 * musste also wissen, wo man nachschauen soll, um zu erfahren, dass es etwas
 * nachzuschauen gibt. Dieses Widget dreht das um: Kommentare, Statuswechsel,
 * Zeiten und Anhänge laufen an einer Stelle zusammen, für Mitarbeiter
 * beschränkt auf ihre Projekte, für Administratoren über alles.
 */
class Geschehen extends Widget
{
    protected string $view = 'filament.widgets.geschehen';

    protected static ?int $sort = 4;

    /** Halbe Breite, neben der eigenen Ticketliste. */
    protected int|string|array $columnSpan = 1;

    /** Wie viele Zeilen gerade gezeigt werden — wächst per "Mehr anzeigen". */
    public int $anzahl = 15;

    public string $umfang = Ereignisstrom::ALLES;

    public string $typ = 'alles';

    /**
     * Der Stand des letzten Besuchs, als ISO-Text.
     *
     * Livewire schickt öffentliche Eigenschaften zwischen Browser und Server
     * hin und her; ein Carbon-Objekt überlebt diesen Weg nicht unverändert.
     * Deshalb Text, und die Umwandlung erst beim Vergleich.
     */
    public ?string $gesehenSeit = null;

    /**
     * Wie viel seit dem letzten Besuch dazugekommen ist. Wird beim Aufbau
     * einmal ermittelt und danach festgehalten — sonst zählte die Zahl bei
     * jedem Neuzeichnen herunter, während man noch liest.
     */
    public int $neu = 0;

    public function mount(): void
    {
        $nutzer = auth()->user();

        if ($nutzer === null) {
            return;
        }

        $marke = DashboardBesuch::marke($nutzer);

        $this->gesehenSeit = $marke?->toIso8601String();
        $this->neu = Ereignisstrom::anzahlSeit($nutzer, $marke);
    }

    public function mehrAnzeigen(): void
    {
        $this->anzahl += 15;
    }

    public function setzeUmfang(string $umfang): void
    {
        $this->umfang = $umfang;
        $this->anzahl = 15;
    }

    public function setzeTyp(string $typ): void
    {
        $this->typ = $typ;
        $this->anzahl = 15;
    }

    /** @return Collection<int, Ereignis> */
    public function getEreignisse(): Collection
    {
        $nutzer = auth()->user();

        if ($nutzer === null) {
            return collect();
        }

        return Ereignisstrom::fuer($nutzer, $this->anzahl, $this->umfang, $this->typ);
    }

    public function getGesehenSeit(): ?Carbon
    {
        return $this->gesehenSeit ? Carbon::parse($this->gesehenSeit) : null;
    }

    /**
     * Ob es sich lohnt, den "Mehr anzeigen"-Knopf zu zeigen: nur wenn die
     * Liste randvoll ist. Sonst stünde er unter einer kurzen Liste und täte
     * beim Drücken nichts.
     */
    public function hatMehr(int $gezeigt): bool
    {
        return $gezeigt >= $this->anzahl;
    }

    /** @return array<string, string> */
    public function umfaenge(): array
    {
        return [
            Ereignisstrom::ALLES => 'Alles',
            Ereignisstrom::MEINE => 'Meine Tickets',
            Ereignisstrom::ANDERE => 'Von anderen',
        ];
    }

    /** @return array<string, string> */
    public function typen(): array
    {
        return [
            'alles' => 'Alles',
            Ereignis::KOMMENTAR => 'Kommentare',
            Ereignis::AENDERUNG => 'Änderungen',
            Ereignis::ZEIT => 'Zeiten',
            Ereignis::ANHANG => 'Anhänge',
        ];
    }

    public function ohneZuordnung(): bool
    {
        return Sichtbarkeit::ohneProjekte();
    }

    /** Ist die Liste eingeschränkt? Entscheidet den Text im Leerzustand. */
    public function gefiltert(): bool
    {
        return $this->umfang !== Ereignisstrom::ALLES || $this->typ !== 'alles';
    }
}
