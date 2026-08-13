<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Enums\Prioritaet;
use App\Models\Project;
use App\Models\TicketStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('titel')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('beschreibung')
                            ->label('Beschreibung')
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),

                Section::make('Zuordnung')
                    ->columns(2)
                    ->schema([
                        Select::make('project_id')
                            ->label('Projekt')
                            ->required()
                            ->searchable()
                            ->preload()
                            // Die Auswahl ist bereits auf sichtbare Projekte
                            // begrenzt; ein Mitarbeiter kann also gar kein
                            // Ticket in einem fremden Projekt anlegen.
                            ->options(fn () => Project::query()
                                ->sichtbarFuer(auth()->user())
                                ->with('customer')
                                ->get()
                                ->sortBy(fn ($p) => $p->customer->name.$p->name)
                                ->mapWithKeys(fn ($p) => [
                                    $p->id => "{$p->customer->kuerzel} — {$p->name}",
                                ]))
                            // Beim Bearbeiten gesperrt: ein Projektwechsel
                            // würde den Kunden und damit den Nummernkreis
                            // wechseln, die vergebene Nummer aber nicht. Die
                            // Kennung zeigte danach auf den falschen Kunden.
                            ->disabled(fn (string $operation) => $operation === 'edit')
                            ->dehydrated(fn (string $operation) => $operation === 'create')
                            ->helperText(fn (string $operation) => $operation === 'edit'
                                ? 'Nicht änderbar — die Ticketnummer gehört zum Kunden dieses Projekts.'
                                : null),

                        Select::make('ticket_status_id')
                            ->label('Status')
                            ->relationship('status', 'name', fn ($query) => $query->sortiert())
                            ->default(fn () => TicketStatus::standard()?->id)
                            ->required()
                            ->preload(),

                        Select::make('prioritaet')
                            ->label('Priorität')
                            ->options(Prioritaet::class)
                            ->default(Prioritaet::Normal->value)
                            ->required(),

                        Select::make('assigned_to')
                            ->label('Zuständig')
                            ->relationship('zustaendig', 'name', fn ($query) => $query->where('aktiv', true)->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->placeholder('Niemand'),

                        DatePicker::make('faellig_am')
                            ->label('Fällig am')
                            ->displayFormat('d.m.Y')
                            ->native(false),
                    ]),
            ]);
    }
}
