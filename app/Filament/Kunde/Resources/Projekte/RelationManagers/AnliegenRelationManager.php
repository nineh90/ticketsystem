<?php

namespace App\Filament\Kunde\Resources\Projekte\RelationManagers;

use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Die Anliegen dieses Projekts, direkt unter dem Projekt.
 *
 * Bewusst ohne eigene Ansehen-Seite: der Knopf führt in die Anliegen-Ressource
 * hinüber, wo Antworten und Dateien hängen. Zwei Detailansichten desselben
 * Tickets wären zwei Stellen, an denen dieselbe Regel gelten müsste.
 */
class AnliegenRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static ?string $title = 'Anliegen';

    protected static ?string $modelLabel = 'Anliegen';

    protected static ?string $pluralModelLabel = 'Anliegen';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-chat-bubble-left-right';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('status'))
            ->columns([
                TextColumn::make('kennung')
                    ->label('Nr.')
                    ->state(fn (Ticket $record) => $record->kennung()),

                TextColumn::make('art')
                    ->label('Art')
                    ->badge(),

                TextColumn::make('titel')
                    ->label('Anliegen')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('status.name')
                    ->label('Stand')
                    ->badge()
                    ->color(fn (Ticket $record) => Color::hex($record->status?->farbe ?? '#9ca3af'))
                    ->description(fn (Ticket $record) => $record->wartetAufKunde() ? 'Sie sind am Zug' : null),

                TextColumn::make('updated_at')
                    ->label('Zuletzt')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('oeffnen')
                    ->label('Ansehen')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (Ticket $record) => AnliegenResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('Keine Anliegen')
            ->emptyStateDescription('Zu diesem Projekt ist gerade nichts offen.');
    }
}
