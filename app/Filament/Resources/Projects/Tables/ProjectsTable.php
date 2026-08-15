<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjektStatus;
use App\Support\Sichtbarkeit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.kuerzel')
                    ->label('Kunde')
                    ->badge()
                    ->color(fn ($record) => $record->customer->farbe ? 'primary' : 'gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Projekt')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record) => $record->customer->name),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('phase')
                    ->label('Stand (Kunde)')
                    ->badge()
                    ->sortable(),

                // Woran man auf einen Blick sieht, wo noch eine Adresse
                // fehlt — und wo es bewusst keine gibt. Ohne die Spalte
                // müsste man elf Projekte einzeln aufmachen, um die eine zu
                // finden, bei der der Kunde vor einer Seite ohne Knopf steht.
                TextColumn::make('adressen')
                    ->label('Vorschau / Live')
                    ->state(fn ($record) => match (true) {
                        filled($record->live_url) && filled($record->demo_url) => 'beide',
                        filled($record->live_url) => 'nur live',
                        filled($record->demo_url) => 'nur Vorschau',
                        default => '—',
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === '—' ? 'gray' : 'success')
                    ->tooltip(fn ($record) => $record->aktuelleAdresse() ?? 'Keine Adresse hinterlegt'),

                TextColumn::make('offene_tickets')
                    ->label('Offen')
                    ->alignEnd()
                    ->state(fn ($record) => $record->tickets()->offen()->count())
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('tickets_count')
                    ->label('Tickets gesamt')
                    ->counts('tickets')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stunden')
                    ->label('Stunden')
                    ->alignEnd()
                    ->state(function ($record) {
                        $ist = $record->erfassteStunden();

                        return $record->budget_stunden
                            ? number_format($ist, 2, ',', '.').' / '.number_format((float) $record->budget_stunden, 2, ',', '.')
                            : number_format($ist, 2, ',', '.');
                    })
                    ->color(fn ($record) => $record->budget_stunden
                        && $record->erfassteStunden() > (float) $record->budget_stunden
                            ? 'danger'
                            : 'gray'),

                TextColumn::make('mitarbeiter_count')
                    ->label('Team')
                    ->counts('mitarbeiter')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('customer')
                    ->label('Kunde')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ProjektStatus::class)
                    // Abgeschlossene Projekte sammeln sich an und verstellen
                    // sonst mit der Zeit den Blick auf die laufenden.
                    ->default(ProjektStatus::Aktiv->value),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Löschen'),
                ]),
            ])
            ->emptyStateHeading(fn () => Sichtbarkeit::ueberschrift('Noch keine Projekte'))
            ->emptyStateDescription(fn () => Sichtbarkeit::beschreibung(
                'Projekte hängen an einem Kunden — leg zuerst einen Kunden an.',
            ));
    }
}
