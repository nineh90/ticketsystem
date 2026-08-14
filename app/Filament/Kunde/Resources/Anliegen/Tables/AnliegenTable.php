<?php

namespace App\Filament\Kunde\Resources\Anliegen\Tables;

use App\Enums\TicketArt;
use App\Models\Project;
use App\Models\Ticket;
use Filament\Actions\ViewAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AnliegenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kennung')
                    ->label('Nr.')
                    ->state(fn (Ticket $record) => $record->kennung())
                    ->weight('medium')
                    ->searchable(query: fn ($query, string $search) => $query->where('nummer', 'like', "%{$search}%")),

                TextColumn::make('art')
                    ->label('Art')
                    ->badge(),

                TextColumn::make('titel')
                    ->label('Anliegen')
                    ->wrap()
                    ->searchable()
                    ->description(fn (Ticket $record) => $record->project?->name),

                TextColumn::make('status.name')
                    ->label('Stand')
                    ->badge()
                    ->color(fn (Ticket $record) => Color::hex($record->status?->farbe ?? '#9ca3af'))
                    // Der Hinweis steht ausdrücklich an der Zeile und nicht
                    // nur als Farbe: "Warten auf Kunde" ist unsere Sprache
                    // und heißt aus Kundensicht das Gegenteil — nämlich dass
                    // WIR warten.
                    ->description(fn (Ticket $record) => $record->wartetAufKunde()
                        ? 'Sie sind am Zug'
                        : null),

                TextColumn::make('created_at')
                    ->label('Gemeldet')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Zuletzt')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Was Aufmerksamkeit braucht, steht oben: erst die eigenen
            // Aufgaben, dann das Neueste.
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['status', 'project']))
            ->filters([
                SelectFilter::make('art')
                    ->label('Art')
                    ->options(collect(TicketArt::cases())
                        ->mapWithKeys(fn (TicketArt $art) => [$art->value => $art->getLabel()])
                        ->all()),

                SelectFilter::make('project_id')
                    ->label('Projekt')
                    ->options(fn () => Project::query()
                        ->sichtbarFuer(auth()->user())
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    // Bei einem einzigen Projekt ist der Filter sinnlos.
                    ->visible(fn () => Project::query()->sichtbarFuer(auth()->user())->count() > 1),
            ])
            ->recordActions([
                ViewAction::make()->label('Ansehen'),
            ])
            // Keine Massenaktionen: das einzige, was dort stünde, wäre
            // Löschen — und ein Kunde soll seine Meldungen nicht wegräumen
            // können, schon gar nicht mehrere auf einmal.
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('Noch nichts gemeldet')
            ->emptyStateDescription('Hier stehen alle Anliegen zu Ihren Projekten — auch die, die wir selbst angelegt haben.');
    }
}
