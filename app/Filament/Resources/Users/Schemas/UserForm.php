<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Rolle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Person')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-Mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            // ignoreRecord, sonst meldet das Formular beim
                            // Bearbeiten die eigene Adresse als vergeben.
                            ->unique(ignoreRecord: true),

                        TextInput::make('password')
                            ->label('Passwort')
                            ->password()
                            ->revealable()
                            ->minLength(10)
                            // Nur beim Anlegen Pflicht. Beim Bearbeiten leer
                            // lassen heißt "unverändert" — ohne das dehydrated-
                            // Filter unten würde ein leeres Feld das Passwort
                            // überschreiben und den Zugang zerstören.
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                            ->helperText('Mindestens 10 Zeichen. Beim Bearbeiten leer lassen, um es nicht zu ändern.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Zugang')
                    ->description('Ein Konto allein reicht nicht — ohne Freigabe kommt niemand ins Dashboard.')
                    ->columns(2)
                    ->schema([
                        Select::make('rolle')
                            ->label('Rolle')
                            ->options(Rolle::class)
                            ->default(Rolle::Mitarbeiter->value)
                            ->required()
                            ->live()
                            ->helperText('Administratoren sehen und verwalten alles.'),

                        Select::make('customer_id')
                            ->label('Kunde')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            // Nur für die Rolle "kunde" sinnvoll, die in v1
                            // nicht vergeben wird.
                            ->visible(fn ($get) => $get('rolle') === Rolle::Kunde->value)
                            ->helperText('Nur für Kundenzugänge (kommt später).'),

                        Toggle::make('panel_zugang')
                            ->label('Zugang freigegeben')
                            ->helperText('Erst hiermit kommt die Person ins Dashboard.'),

                        Toggle::make('aktiv')
                            ->label('Aktiv')
                            ->default(true)
                            ->helperText('Ausgeschiedene Personen deaktivieren statt löschen — ihre Tickets und Zeiten bleiben zuordenbar.'),
                    ]),

                Section::make('Projekte')
                    ->description('Mitarbeiter sehen ausschließlich die Projekte, die hier zugeordnet sind. Für Administratoren ohne Bedeutung — sie sehen ohnehin alles.')
                    ->schema([
                        Select::make('projects')
                            ->label('Zugeordnete Projekte')
                            ->relationship('projects', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            // Im Auswahlfeld den Kunden mitzeigen: zwei Kunden
                            // dürfen beide ein Projekt "Website" haben.
                            ->getOptionLabelFromRecordUsing(
                                fn ($record) => $record->customer->name.' — '.$record->name,
                            ),
                    ])
                    ->visible(fn ($get) => $get('rolle') === Rolle::Mitarbeiter->value),
            ]);
    }
}
