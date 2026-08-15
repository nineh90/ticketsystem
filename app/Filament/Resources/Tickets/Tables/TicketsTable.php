<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Enums\Prioritaet;
use App\Enums\Quelle;
use App\Enums\TicketArt;
use App\Filament\Resources\Tickets\TicketResource;
use App\Support\Sichtbarkeit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kennung')
                    ->label('Nr.')
                    ->state(fn ($record) => $record->kennung())
                    ->badge()
                    ->color('gray')
                    // Nach der echten Spalte sortieren, nicht nach dem
                    // zusammengesetzten Text — sonst käme LDX-10 vor LDX-2.
                    ->sortable(['nummer'])
                    ->searchable(['nummer']),

                TextColumn::make('art')
                    ->label('Art')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('titel')
                    ->label('Titel')
                    ->searchable()
                    ->wrap()
                    ->weight('medium')
                    // Bei Kundenmeldungen steht es an der Zeile: das ist die
                    // Angabe, die über die Reihenfolge des Tages entscheidet,
                    // und sie soll nicht hinter einer ausblendbaren Spalte
                    // liegen.
                    ->description(fn ($record) => $record->istVomKunden()
                        ? $record->project->name.' · vom Kunden gemeldet'
                        : $record->project->name),

                TextColumn::make('customer.name')
                    ->label('Kunde')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => Color::hex($record->status->farbe))
                    ->sortable(),

                TextColumn::make('prioritaet')
                    ->label('Priorität')
                    ->badge()
                    ->sortable(),

                TextColumn::make('zustaendig.name')
                    ->label('Zuständig')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('faellig_am')
                    ->label('Fällig')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn ($record) => $record->faellig_am
                        && $record->faellig_am->isPast()
                        && ! $record->erledigt_at
                            ? 'danger'
                            : null),

                TextColumn::make('quelle')
                    ->label('Herkunft')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Angelegt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('customer')
                    ->label('Kunde')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('project')
                    ->label('Projekt')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('ticket_status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->multiple(),

                SelectFilter::make('prioritaet')
                    ->label('Priorität')
                    ->options(Prioritaet::class)
                    ->multiple(),

                SelectFilter::make('assigned_to')
                    ->label('Zuständig')
                    ->relationship('zustaendig', 'name', fn ($query) => $query->intern()->orderBy('name'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('quelle')
                    ->label('Herkunft')
                    ->options(Quelle::class),

                SelectFilter::make('art')
                    ->label('Art')
                    ->options(TicketArt::class)
                    ->multiple(),

                // "Nur meine", "Nur offene", "Überfällig" und "Unzugewiesen"
                // standen hier einmal als Schalter. Sie sind jetzt Reiter über
                // der Liste (siehe ListTickets) — sichtbar statt hinter einem
                // Menü, und mit Zahl daneben.
                //
                // Sie hier zusätzlich stehen zu lassen wäre nicht bloß
                // doppelt, sondern falsch: "Nur offene" war voreingestellt und
                // hätte den Reiter "Erledigt" auf eine dauerhaft leere Liste
                // zeigen lassen.
            ])
            // Filter über der Liste statt hinter dem Trichter, zusammengeklappt
            // — sonst nähme die Filterzeile mehr Platz ein als die ersten
            // Ticketzeilen. Ein Klick öffnet sie, und die gesetzten Filter
            // stehen auch im geschlossenen Zustand als Abzeichen daneben.
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            // Klick auf die Zeile führt auf die Detailseite mit Kommentaren,
            // Zeiten und Verlauf — nicht direkt ins Bearbeiten-Formular.
            ->recordUrl(fn ($record) => TicketResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->label('Öffnen'),
                EditAction::make()->label('Bearbeiten'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Löschen'),
                ]),
            ])
            ->emptyStateHeading(fn () => Sichtbarkeit::ueberschrift('Keine Tickets'))
            ->emptyStateDescription(fn () => Sichtbarkeit::beschreibung(
                'Entweder gibt es noch keine, oder die Filter sind zu eng gesetzt.',
            ));
    }
}
