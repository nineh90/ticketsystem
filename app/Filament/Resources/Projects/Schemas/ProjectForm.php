<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjektStatus;
use App\Models\Customer;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Projekt')
                    ->columns(2)
                    ->schema([
                        Select::make('customer_id')
                            ->label('Kunde')
                            ->relationship(
                                'customer',
                                'name',
                                // Inaktive Kunden nicht anbieten: ein neues
                                // Projekt für einen stillgelegten Kunden ist
                                // fast immer ein Versehen.
                                fn ($query) => $query->aktiv()->orderBy('name'),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(
                                fn (Customer $record) => "{$record->kuerzel} — {$record->name}",
                            ),

                        Select::make('status')
                            ->label('Status')
                            ->options(ProjektStatus::class)
                            ->default(ProjektStatus::Aktiv->value)
                            ->required(),

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
                            ->maxLength(255)
                            // Nur innerhalb desselben Kunden eindeutig — zwei
                            // Kunden dürfen beide ein Projekt "website" haben.
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, $get) => $rule->where('customer_id', $get('customer_id')),
                            ),

                        TextInput::make('budget_stunden')
                            ->label('Budget (Stunden)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.25),

                        ColorPicker::make('farbe')
                            ->label('Farbe'),

                        Textarea::make('beschreibung')
                            ->label('Beschreibung')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Kundenbereich')
                    ->description('Was der Kunde unter /kunde zu diesem Projekt sieht. Zeitbuchungen, Budget und interne Beschreibung bleiben in jedem Fall drinnen.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('kunden_sichtbar')
                            ->label('Für den Kunden sichtbar')
                            ->default(true)
                            ->helperText('Aus: das Projekt verschwindet aus seinem Bereich — samt aller Anliegen dazu, auch der selbst gemeldeten. Für Angebote, die noch nicht besprochen sind.')
                            ->columnSpanFull(),

                        TextInput::make('demo_url')
                            ->label('Live-Fassung')
                            ->url()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->placeholder('https://…')
                            ->helperText('Wo der aktuelle Stand läuft. Steht im Kundenbereich als Knopf "Live ansehen".')
                            ->columnSpanFull(),

                        Textarea::make('kunden_info')
                            ->label('Stand für den Kunden')
                            ->rows(4)
                            ->maxLength(2000)
                            ->helperText('Ein, zwei Sätze, woran gerade gearbeitet wird. Getrennt von der Beschreibung oben — die ist intern.')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make('Team')
                    ->description('Mitarbeiter sehen ausschließlich Projekte, in denen sie hier stehen. Administratoren sehen ohnehin alles und müssen nicht eingetragen werden.')
                    ->schema([
                        Select::make('mitarbeiter')
                            ->label('Zugeordnete Mitarbeiter')
                            ->relationship(
                                'mitarbeiter',
                                'name',
                                fn ($query) => $query->where('aktiv', true)->orderBy('name'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
