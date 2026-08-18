<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Support\Sichtbarkeit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Das Logo, wo es eins gibt — sonst bleibt die Farbmarke.
                // Beides nebeneinander wäre zweimal dieselbe Aussage in
                // einer Zeile, die ohnehin schon acht Spalten hat.
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->visibility('public'),

                ColorColumn::make('farbe')
                    ->label('')
                    ->visible(fn ($record) => blank($record?->logo)),

                TextColumn::make('kuerzel')
                    ->label('Kürzel')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('projects_count')
                    ->label('Projekte')
                    ->counts('projects')
                    ->alignEnd()
                    ->sortable(),

                // Offene Tickets sind die Zahl, die im Alltag interessiert —
                // die Gesamtzahl sagt nach einem Jahr nichts mehr.
                TextColumn::make('offene_tickets')
                    ->label('Offen')
                    ->alignEnd()
                    ->state(fn ($record) => $record->tickets()->offen()->count())
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('betreuung')
                    ->label('Betreuung')
                    ->badge()
                    ->sortable(),

                // Der Hauptkontakt statt der früheren Spalte
                // "ansprechpartner" — die steht noch in der Datenbank, ist
                // aber seit dem Umstieg auf die Kontakte-Tabelle nicht mehr
                // die Wahrheit.
                TextColumn::make('hauptkontakt')
                    ->label('Ansprechpartner')
                    ->state(fn ($record) => $record->hauptkontakt()?->name)
                    ->placeholder('keiner hinterlegt')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('vertrag_bis')
                    ->label('Vertrag bis')
                    ->date('d.m.Y')
                    ->placeholder('unbefristet')
                    // Was in den nächsten zwei Monaten ausläuft, soll beim
                    // Überfliegen auffallen — danach zu fragen kommt niemand
                    // von selbst.
                    ->color(fn ($record) => $record->vertrag_bis?->isBefore(now()->addMonths(2)) ? 'warning' : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('mitarbeiter_count')
                    ->label('Team')
                    ->counts('mitarbeiter')
                    ->alignEnd()
                    ->tooltip('Mitarbeiter, die diesen Kunden komplett betreuen'),

                IconColumn::make('aktiv')
                    ->label('Aktiv')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('aktiv')
                    ->label('Aktiv')
                    ->default(true),
            ])
            ->recordActions([
                // Ansehen zuerst und als Standardweg: die Akte trägt jetzt
                // Zahlen und Verläufe, und die will man öfter sehen als ein
                // Feld ändern.
                ViewAction::make()->label('Ansehen'),
                EditAction::make()->label('Bearbeiten'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Löschen'),
                ]),
            ])
            ->emptyStateHeading(fn () => Sichtbarkeit::ueberschrift('Noch keine Kunden'))
            ->emptyStateDescription(fn () => Sichtbarkeit::beschreibung(
                'Lege einen Kunden an, darunter kommen die Projekte.',
            ));
    }
}
