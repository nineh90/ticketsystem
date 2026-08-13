<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Support\Sichtbarkeit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('farbe')
                    ->label(''),

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

                TextColumn::make('ansprechpartner')
                    ->label('Ansprechpartner')
                    ->searchable()
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
