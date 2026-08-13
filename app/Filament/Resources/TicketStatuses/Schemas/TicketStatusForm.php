<?php

namespace App\Filament\Resources\TicketStatuses\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TicketStatusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, $set) {
                        // Slug nur beim Anlegen ableiten: er wird von Seedern
                        // und später von der n8n-Schnittstelle als fester
                        // Bezeichner genutzt. Ein Umbenennen darf ihn nicht
                        // mitziehen.
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),

                TextInput::make('slug')
                    ->label('Kürzel')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Fester Bezeichner. Bleibt beim Umbenennen unverändert.'),

                ColorPicker::make('farbe')
                    ->label('Farbe')
                    ->required()
                    ->default('#9ca3af'),

                TextInput::make('sortierung')
                    ->label('Reihenfolge')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Bestimmt die Reihenfolge der Spalten im Kanban. Kleiner steht weiter links.'),

                Toggle::make('ist_abschluss')
                    ->label('Schließt das Ticket ab')
                    ->helperText('Tickets in diesem Stadium gelten als erledigt und zählen nicht mehr als offen. Beim Wechsel hierher wird der Erledigt-Zeitpunkt gesetzt.')
                    ->columnSpanFull(),
            ]);
    }
}
