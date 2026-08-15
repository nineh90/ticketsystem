<?php

namespace App\Filament\Kunde\Pages;

use App\Models\Zugangsdaten;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * "Zugangsdaten" im Kundenbereich.
 *
 * Der Ort, an dem der Kunde nachsieht, wie er in seine eigene Seite kommt —
 * das WordPress der neuen Website, die Basic-Auth vor der Vorschau. Bisher
 * standen solche Daten in einer Mail vom Tag der Übergabe, und die war nach
 * drei Monaten nicht mehr auffindbar. Die Folge war jedes Mal dieselbe: ein
 * Anruf bei uns, und wir suchten sie in unseren eigenen Notizen.
 *
 * Zu sehen ist ausschließlich, was am Eintrag ausdrücklich freigegeben ist
 * (Zugangsdaten::sichtbarFuer). Unsere Server-, Hoster- und DNS-Zugänge
 * liegen in derselben Tabelle und erscheinen hier nie.
 */
class Zugaenge extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Zugangsdaten';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'zugangsdaten';

    protected string $view = 'filament.kunde.pages.zugaenge';

    public function getTitle(): string
    {
        return 'Zugangsdaten';
    }

    public function getSubheading(): ?string
    {
        return 'Ihre Anmeldedaten. Passwörter werden erst auf Klick sichtbar.';
    }

    /**
     * Alle sichtbaren Einträge, gruppiert nach Projekt.
     *
     * Die allgemeinen (ohne Projekt) stehen unter einer eigenen Überschrift
     * und zuerst — sie gelten für alles und sind meist die, die man sucht.
     *
     * @return Collection<string, Collection<int, Zugangsdaten>>
     */
    public function getGruppen(): Collection
    {
        return Zugangsdaten::query()
            ->sichtbarFuer(auth()->user())
            ->with('project')
            ->inReihenfolge()
            ->get()
            ->groupBy(fn (Zugangsdaten $eintrag) => $eintrag->project?->name ?? 'Allgemein')
            ->sortKeysUsing(fn (string $a, string $b) => match (true) {
                $a === 'Allgemein' => -1,
                $b === 'Allgemein' => 1,
                default => strcasecmp($a, $b),
            });
    }
}
