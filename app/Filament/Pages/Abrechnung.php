<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
use App\Support\Abrechnung as Rechnen;
use App\Support\Dauer;
use App\Support\Sichtbarkeit;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Was noch auf keiner Rechnung steht.
 *
 * Die Seite beantwortet die eine Frage, die vor jeder Rechnung stand und
 * bisher Handarbeit war: bei wem ist abrechenbare Zeit aufgelaufen, seit
 * zuletzt abgerechnet wurde. Der Schalter "abrechenbar" hing seit dem ersten
 * Tag an jeder Buchung und wurde von nichts ausgewertet — er war eine
 * Angabe, die man macht und nie wiedersieht.
 *
 * Sie rechnet nichts aus, was auf die Rechnung gehört. Stundensätze stehen
 * nirgends im System, und das soll so bleiben: die Rechnung entsteht in
 * sevDesk, hier steht nur, wofür. Deshalb Stunden und keine Beträge.
 *
 * Eigene Seite und kein Widget auf dem Betrieb-Dashboard: man kommt einmal
 * im Monat her, mit einer bestimmten Absicht, und arbeitet die Liste von oben
 * nach unten ab. Das ist ein Vorgang, keine Kennzahl.
 */
class Abrechnung extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Abrechnung';

    protected static ?string $title = 'Abrechnung';

    /** Hinter Kanban, vor der Verwaltung. */
    protected static ?int $navigationSort = 46;

    protected string $view = 'filament.pages.abrechnung';

    public function getSubheading(): ?string
    {
        return 'Abrechenbare Zeit, die noch keiner Rechnung zugeordnet ist.';
    }

    /**
     * @return Collection<int, object>
     */
    public function getZeilen(): Collection
    {
        /** @var User $nutzer */
        $nutzer = auth()->user();

        return Rechnen::jeKunde($nutzer);
    }

    /** Die Summe über alles, was unten steht. */
    public function getGesamtMinuten(): int
    {
        return (int) $this->getZeilen()->sum('minuten');
    }

    public function alsStunden(int $minuten): string
    {
        return Dauer::alsStunden($minuten);
    }

    /**
     * Der Weg zum Kunden — und zwar zu seiner Akte, wo die Dokumente stehen.
     * Dort wird die Rechnung hochgeladen und dort werden ihr die Zeiten
     * zugeordnet; ein Sprung in eine Zeitenliste wäre einer zu wenig.
     */
    public function kundeUrl(int $id): string
    {
        return CustomerResource::getUrl('view', ['record' => $id]);
    }

    public function ohneZuordnung(): bool
    {
        return Sichtbarkeit::ohneProjekte();
    }
}
