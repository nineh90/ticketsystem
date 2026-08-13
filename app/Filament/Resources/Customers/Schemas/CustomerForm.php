<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kunde')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            // Slug und Kürzel nur beim Anlegen vorschlagen.
                            // Beim Bearbeiten würden sie sonst mitwandern —
                            // und ein geändertes Kürzel ändert rückwirkend die
                            // Kennung aller bestehenden Tickets dieses Kunden.
                            ->afterStateUpdated(function (string $operation, $state, $set, $get) {
                                if ($operation !== 'create') {
                                    return;
                                }

                                $set('slug', Str::slug($state));

                                if (blank($get('kuerzel'))) {
                                    $set('kuerzel', Str::upper(Str::substr(Str::slug($state), 0, 3)));
                                }
                            }),

                        TextInput::make('kuerzel')
                            ->label('Kürzel')
                            ->required()
                            ->maxLength(5)
                            ->minLength(2)
                            ->unique(ignoreRecord: true)
                            ->alphaNum()
                            ->helperText('Steht in jeder Ticketnummer: LDX-42. Nachträglich ändern benennt alle Tickets dieses Kunden um.'),

                        TextInput::make('slug')
                            ->label('Kürzel für URLs')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        ColorPicker::make('farbe')
                            ->label('Farbe')
                            ->default('#00bcd4')
                            ->helperText('Für die farbige Markierung in Listen.'),
                    ]),

                Section::make('Kontakt')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('ansprechpartner')
                            ->label('Ansprechpartner')
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-Mail')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('telefon')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(255),

                        Textarea::make('notizen')
                            ->label('Notizen')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Zuständige Mitarbeiter')
                    ->description('Wer hier steht, sieht alle Projekte und Tickets dieses Kunden — auch künftige. Administratoren müssen nicht eingetragen werden.')
                    ->schema([
                        Select::make('mitarbeiter')
                            ->label('Mitarbeiter')
                            ->relationship(
                                'mitarbeiter',
                                'name',
                                fn ($query) => $query->where('aktiv', true)->orderBy('name'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('aktiv')
                            ->label('Aktiv')
                            ->default(true)
                            ->helperText('Inaktive Kunden verschwinden aus Auswahllisten, ihre Tickets und Zeiten bleiben erhalten.'),
                    ]),
            ]);
    }
}
