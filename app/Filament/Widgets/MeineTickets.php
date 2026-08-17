<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Support\Sichtbarkeit;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Die persönliche Arbeitsliste: was mir zugewiesen und noch offen ist.
 */
class MeineTickets extends TableWidget
{
    /** Unter den eigenen Zahlen, der Uhr und den offenen Nachrichten. */
    protected static ?int $sort = 4;

    /**
     * Volle Breite.
     *
     * Stand vorher halb, mit dem Geschehen daneben — das ist mit der Trennung
     * auf die Betriebsseite gewandert. Der Ticketliste kommt das entgegen:
     * halbe Breite war für Titel, Kunde und Projekt ohnehin knapp, deshalb
     * gab es dafür überhaupt Raster::HALB mit seiner xl-Schwelle.
     */
    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Meine offenen Tickets';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Ticket::query()
                ->sichtbarFuer(auth()->user())
                ->offen()
                ->where('assigned_to', auth()->id())
                ->with(['customer', 'project', 'status']))
            // Sortierung nach Dringlichkeit: was überfällig ist, zuerst;
            // danach das mit dem nächsten Termin. Tickets ohne Termin ganz
            // ans Ende, statt sie durch NULL-Sortierung nach vorne zu
            // schwemmen.
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('faellig_am IS NULL')
                ->orderBy('faellig_am')
                ->orderByRaw("CASE prioritaet
                    WHEN 'dringend' THEN 0
                    WHEN 'hoch' THEN 1
                    WHEN 'normal' THEN 2
                    ELSE 3 END"))
            ->columns([
                // Die Priorität steckt in der Farbe der Nummer, statt eine
                // eigene Spalte zu belegen: "dringend" erkennt man an Rot
                // schneller als am gelesenen Wort, und eine Spalte weniger
                // ist eine Spalte, die auf einem Laptop nicht umbricht.
                TextColumn::make('kennung')
                    ->label('Nr.')
                    ->state(fn (Ticket $record) => $record->kennung())
                    ->badge()
                    ->color(fn (Ticket $record) => $record->prioritaet->getColor())
                    ->tooltip(fn (Ticket $record) => 'Priorität: '.$record->prioritaet->getLabel()),

                TextColumn::make('titel')
                    ->label('Titel')
                    ->wrap()
                    ->weight('medium')
                    ->description(fn (Ticket $record) => $record->customer->name.' · '.$record->project->name),

                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Ticket $record) => Color::hex($record->status->farbe)),

                TextColumn::make('faellig_am')
                    ->label('Fällig')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->color(fn (Ticket $record) => $record->faellig_am && $record->faellig_am->isPast()
                        ? 'danger'
                        : null),
            ])
            ->recordUrl(fn (Ticket $record) => TicketResource::getUrl('view', ['record' => $record]))
            // Fünf Zeilen, weil eine Zeile mit umbrochenem Titel und
            // Kunde/Projekt darunter fast hundert Pixel hoch ist. Bei zehn
            // wäre diese Karte doppelt so hoch wie der Strom daneben, und die
            // rechte Spalte endete in einem Feld aus Nichts.
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(fn () => Sichtbarkeit::ueberschrift('Nichts offen'))
            ->emptyStateDescription(fn () => Sichtbarkeit::beschreibung(
                'Dir ist gerade kein offenes Ticket zugewiesen.',
            ))
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
