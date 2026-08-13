<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\ProjektStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Projekte direkt auf der Kundenseite — der übliche Weg, ein Projekt
 * anzulegen, weil man dabei ohnehin beim Kunden ist.
 */
class ProjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'projects';

    protected static ?string $title = 'Projekte';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, $set) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug($state));
                    }
                }),

            TextInput::make('slug')
                ->label('Kürzel für URLs')
                ->required()
                ->maxLength(255),

            Select::make('status')
                ->label('Status')
                ->options(ProjektStatus::class)
                ->default(ProjektStatus::Aktiv->value)
                ->required(),

            TextInput::make('budget_stunden')
                ->label('Budget (Stunden)')
                ->numeric()
                ->minValue(0)
                ->step(0.25)
                ->helperText('Optional. Dient dem Soll-Ist-Vergleich gegen die erfassten Zeiten.'),

            Textarea::make('beschreibung')
                ->label('Beschreibung')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Projekt')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->alignEnd(),

                TextColumn::make('offene_tickets')
                    ->label('Offen')
                    ->alignEnd()
                    ->state(fn ($record) => $record->tickets()->offen()->count())
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('stunden')
                    ->label('Stunden')
                    ->alignEnd()
                    ->state(function ($record) {
                        $ist = $record->erfassteStunden();

                        return $record->budget_stunden
                            ? number_format($ist, 2, ',', '.').' / '.number_format((float) $record->budget_stunden, 2, ',', '.')
                            : number_format($ist, 2, ',', '.');
                    })
                    // Warnt, sobald das Budget überschritten ist — die Zahl
                    // allein übersieht man in einer Tabelle leicht.
                    ->color(fn ($record) => $record->budget_stunden
                        && $record->erfassteStunden() > (float) $record->budget_stunden
                            ? 'danger'
                            : 'gray'),
            ])
            ->defaultSort('name')
            ->headerActions([
                CreateAction::make()->label('Projekt anlegen'),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
            ])
            ->emptyStateHeading('Noch keine Projekte')
            ->emptyStateDescription('Ohne Projekt lässt sich für diesen Kunden kein Ticket anlegen.');
    }
}
