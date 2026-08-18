<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Widgets\KundeKennzahlen;
use App\Filament\Resources\Customers\Widgets\KundeTicketaufkommen;
use App\Filament\Resources\Customers\Widgets\KundeZeitverlauf;
use App\Models\Customer;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Die Kundenakte zum Ansehen.
 *
 * Vorher führte jeder Weg zu einem Kunden direkt ins Bearbeiten-Formular.
 * Das hatte eine unangenehme Folge: um nachzusehen, wie es um jemanden
 * steht, musste man ein Formular öffnen, in dem jedes Feld änderbar ist —
 * und daneben stand keine einzige Zahl. Wie viel Zeit in diesen Kunden
 * geflossen ist, was noch offen ist, ob er etwas schuldet: alles das musste
 * man sich aus drei Listen zusammensuchen.
 *
 * Diese Seite dreht das um. Oben die vier Zahlen, darunter zwei Verläufe,
 * darunter die Stammdaten zum Lesen und die Reiter mit Projekten,
 * Dokumenten, Kontakten, Zugangsdaten und Zugängen. Geändert wird über den
 * Knopf oben rechts — das Formular bleibt unverändert, es ist nur nicht
 * mehr der Ort, an dem man landet.
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * Wer die Kundenakte offen hat, hat die Meldungen dazu gesehen.
     *
     * Stand bisher in EditCustomer und wandert mit: die Seite, auf der man
     * landet, ist jetzt diese. Dort bleibt es zusätzlich stehen — wer direkt
     * ins Bearbeiten springt, hat die neue Anschrift genauso vor sich.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        Benachrichtigung::gesehen(auth()->user(), Herkunft::kunde($this->record->getKey()));
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        /** @var Customer $kunde */
        $kunde = $this->record;

        $teile = array_filter([
            $kunde->kuerzel,
            $kunde->betreuung?->getLabel(),
            $kunde->vertragsart,
        ]);

        return implode(' · ', $teile) ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Bearbeiten'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            KundeKennzahlen::class,
            KundeZeitverlauf::class,
            KundeTicketaufkommen::class,
        ];
    }

    /** Zwei Spalten, damit die beiden Verläufe nebeneinander stehen. */
    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    /**
     * Die Stammdaten zum Lesen.
     *
     * Bewusst nur das, was man im Gespräch braucht — und ausdrücklich nicht
     * alles, was im Formular steht. Eine Ansicht, die jedes Feld wiederholt,
     * ist ein zweites Formular ohne Knöpfe: man liest sie einmal und öffnet
     * danach wieder das echte. Interne Notizen stehen deshalb hier, die
     * Kündigungsfrist nicht.
     */
    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Stammdaten')
                ->columns(3)
                ->schema([
                    TextEntry::make('betreuung')
                        ->label('Stand')
                        ->badge(),

                    TextEntry::make('kunde_seit')
                        ->label('Kunde seit')
                        ->date('d.m.Y')
                        ->placeholder('—'),

                    TextEntry::make('vertrag_bis')
                        ->label('Vertrag läuft bis')
                        ->date('d.m.Y')
                        ->placeholder('unbefristet'),

                    TextEntry::make('website')
                        ->label('Website')
                        ->url(fn (Customer $record) => $record->website)
                        ->openUrlInNewTab()
                        ->placeholder('—'),

                    TextEntry::make('hoster')
                        ->label('Hoster')
                        ->placeholder('—'),

                    TextEntry::make('rechnung_email')
                        ->label('Rechnung an')
                        ->copyable()
                        ->placeholder('—'),

                    TextEntry::make('anschrift')
                        ->label('Anschrift')
                        ->state(fn (Customer $record) => $record->anschrift())
                        ->placeholder('—'),

                    TextEntry::make('ust_id')
                        ->label('USt-IdNr.')
                        ->placeholder('—'),

                    TextEntry::make('hauptkontakt')
                        ->label('Hauptkontakt')
                        ->state(fn (Customer $record) => $record->hauptkontakt()?->name)
                        ->placeholder('—'),
                ]),

            Section::make('Interne Notizen')
                ->collapsed()
                // Ohne Notizen keine leere Klappe: eine Überschrift, hinter
                // der nie etwas steht, klappt man einmal auf und danach nie
                // wieder — auch dann nicht, wenn doch etwas darin steht.
                ->visible(fn (Customer $record) => filled($record->notizen))
                ->schema([
                    TextEntry::make('notizen')
                        ->label('')
                        ->prose(),
                ]),
        ]);
    }
}
