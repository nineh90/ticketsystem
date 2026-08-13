<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Support\Verlaufstext;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

/**
 * Der Verlauf eines Tickets.
 *
 * Reine Anzeige — der Verlauf wird von spatie/laravel-activitylog
 * geschrieben und ist nicht bearbeitbar. Ein änderbares Protokoll wäre
 * keines.
 *
 * Die Übersetzung der Feldwerte steht in Verlaufstext, weil dieselben Zeilen
 * auch im Dashboard erscheinen.
 */
class AktivitaetRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Verlauf';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clock';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Wann')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('Wer')
                    // Leer heißt: nicht von einem Menschen ausgelöst, also
                    // über die Schnittstelle (n8n) oder einen Hintergrundlauf.
                    ->placeholder('System / Schnittstelle'),

                TextColumn::make('event')
                    ->label('Was')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'created' => 'angelegt',
                        'updated' => 'geändert',
                        'deleted' => 'gelöscht',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('aenderungen')
                    ->label('Änderungen')
                    ->state(fn (Activity $record) => Verlaufstext::zeilen($record))
                    // Mehrere geänderte Felder gehören untereinander. Ein "\n"
                    // im String reicht nicht — HTML bricht daran nicht um, und
                    // die Zeilen liefen ineinander.
                    ->listWithLineBreaks()
                    ->bulleted(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            // Keine Aktionen: der Verlauf wird gelesen, nicht bearbeitet.
            ->emptyStateHeading('Noch nichts passiert');
    }
}
