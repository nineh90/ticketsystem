<?php

namespace App\Filament\Resources\TicketStatuses\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketStatusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Stadium')
                    ->badge()
                    ->color(fn ($record) => Color::hex($record->farbe)),

                TextColumn::make('slug')
                    ->label('Kürzel')
                    ->color('gray'),

                TextColumn::make('sortierung')
                    ->label('Reihenfolge')
                    ->alignEnd()
                    ->sortable(),

                IconColumn::make('ist_abschluss')
                    ->label('Abschluss')
                    ->boolean(),

                TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->alignEnd(),
            ])
            ->defaultSort('sortierung')
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                // Nur löschbar, solange kein Ticket daran hängt — das prüft
                // TicketStatusPolicy::delete. Ohne diese Bedingung stünde man
                // vor einem Datenbankfehler statt vor einer Erklärung.
                DeleteAction::make()
                    ->label('Löschen')
                    ->hidden(fn ($record) => $record->tickets()->exists()),
            ])
            // Bewusst keine Bulk-Löschung: die Stadien sind der Arbeitsablauf
            // des ganzen Hauses, sie werden einzeln und mit Bedacht angefasst.
            ->emptyStateHeading('Keine Stadien');
    }
}
