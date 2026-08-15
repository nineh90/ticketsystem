<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjektPhase;
use App\Enums\ProjektStatus;
use App\Models\Customer;
use App\Models\Project;
use Filament\Actions\Action;
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
    /**
     * Der Vorschlag für die Vorschau-Adresse, gebaut aus dem Muster in
     * config/demo.php und dem Kürzel des Projekts.
     *
     * null, solange kein Kürzel dasteht oder kein Muster gesetzt ist — dann
     * verschwindet der Knopf, statt eine halbe Adresse anzubieten.
     */
    protected static function demoVorschlag($get): ?string
    {
        return Project::vorschauVorschlag($get('slug'));
    }

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
                            ->label('Status (intern)')
                            ->options(ProjektStatus::class)
                            ->default(ProjektStatus::Aktiv->value)
                            ->required()
                            ->helperText('Ob wir gerade daran arbeiten. Sieht nur das Team.'),

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
                            ->live(onBlur: true)
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

                        // Die Phase ist das Feld, das der Kunde am häufigsten
                        // ansieht — deshalb steht es hier oben und nicht
                        // neben dem internen Status. Sie zu pflegen ist die
                        // kleinste Möglichkeit, eine Nachfrage zu ersparen.
                        Select::make('phase')
                            ->label('Stand für den Kunden')
                            ->options(ProjektPhase::class)
                            ->default(ProjektPhase::Umsetzung->value)
                            ->required()
                            ->live()
                            // $state ist beim Anlegen ein String, beim
                            // Bearbeiten aber schon das Enum — das Model
                            // castet die Spalte. ProjektPhase::from() warf
                            // im zweiten Fall einen TypeError und legte die
                            // ganze Bearbeiten-Seite lahm.
                            ->helperText(function ($state): ?string {
                                $phase = $state instanceof ProjektPhase
                                    ? $state
                                    : ProjektPhase::tryFrom((string) $state);

                                return $phase === null
                                    ? null
                                    : 'Der Kunde liest: „'.$phase->getDescription().'"';
                            })
                            ->columnSpanFull(),

                        TextInput::make('demo_url')
                            ->label('Vorschau')
                            ->url()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-eye')
                            ->placeholder(fn ($get) => static::demoVorschlag($get) ?? 'https://…')
                            ->helperText(fn ($get) => static::demoVorschlag($get) === null
                                ? 'Wo der Zwischenstand liegt. Leer lassen, wenn es keine Vorschau gibt.'
                                : 'Wo der Zwischenstand liegt — genau diese Adresse sieht der Kunde. Der Knopf rechts schlägt unsere Standardadresse vor. Leer lassen, wenn es keine Vorschau gibt.')
                            // Der Vorschlag wird eingesetzt, nicht automatisch
                            // übernommen. Eine still gefüllte Adresse, unter
                            // der nichts läuft, stünde dem Kunden als Knopf
                            // "Vorschau ansehen" gegenüber — und der führt
                            // dann ins Leere.
                            ->suffixAction(
                                Action::make('ausDemoDomain')
                                    ->label('Vorschlagen')
                                    ->icon('heroicon-m-sparkles')
                                    ->tooltip('Adresse aus unserem Demo-Muster einsetzen')
                                    ->visible(fn ($get) => static::demoVorschlag($get) !== null)
                                    ->action(fn ($set, $get) => $set('demo_url', static::demoVorschlag($get))),
                            ),

                        TextInput::make('live_url')
                            ->label('Live-Adresse')
                            ->url()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->placeholder('https://…')
                            ->helperText('Die echte Adresse, sobald es sie gibt. Ab Phase „Live" der Hauptknopf.'),

                        Textarea::make('kunden_info')
                            ->label('Woran wir gerade arbeiten')
                            ->rows(4)
                            ->maxLength(2000)
                            ->helperText('Ein, zwei Sätze im Klartext. Getrennt von der Beschreibung oben — die ist intern.')
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
                                fn ($query) => $query->intern()->orderBy('name'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
