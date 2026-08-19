<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Prioritaet;
use App\Models\TicketStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static ?string $title = 'Tickets';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('titel')
                ->label('Titel')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('ticket_status_id')
                ->label('Status')
                ->relationship('status', 'name', fn ($query) => $query->sortiert())
                ->default(fn () => TicketStatus::standard()?->id)
                ->required(),

            Select::make('prioritaet')
                ->label('Priorität')
                ->options(Prioritaet::class)
                ->default(Prioritaet::Normal->value)
                ->required(),

            Select::make('assigned_to')
                ->label('Zuständig')
                ->relationship('zustaendig', 'name', fn ($query) => $query->intern()->orderBy('name'))
                ->searchable()
                ->preload()
                ->placeholder('Niemand'),

            DatePicker::make('faellig_am')
                ->label('Fällig am')
                ->displayFormat('d.m.Y'),

            Textarea::make('beschreibung')
                ->label('Beschreibung')
                ->rows(5)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kennung')
                    ->label('Nr.')
                    ->state(fn ($record) => $record->kennung())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('titel')
                    ->label('Titel')
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),

                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => Color::hex($record->status->farbe)),

                TextColumn::make('prioritaet')
                    ->label('Priorität')
                    ->badge(),

                TextColumn::make('zustaendig.name')
                    ->label('Zuständig')
                    ->placeholder('—'),

                TextColumn::make('faellig_am')
                    ->label('Fällig')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    // Überfällige offene Tickets rot — das ist die Information,
                    // wegen der man die Spalte überhaupt anschaut.
                    ->color(fn ($record) => $record->faellig_am
                        && $record->faellig_am->isPast()
                        && ! $record->erledigt_at
                            ? 'danger'
                            : null),
            ])
            ->defaultSort('nummer', 'desc')
            ->filters([
                SelectFilter::make('ticket_status_id')
                    ->label('Status')
                    ->relationship('status', 'name'),
            ])
            ->headerActions([
                CreateAction::make()->label('Ticket anlegen'),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
            ])
            ->emptyStateHeading('Noch keine Tickets')
            ->emptyStateDescription('Lege das erste Ticket für dieses Projekt an.');
    }
}
