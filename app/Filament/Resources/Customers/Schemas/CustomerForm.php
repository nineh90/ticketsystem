<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\Betreuung;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Die Kundenakte.
 *
 * Die früheren Einzelfelder ansprechpartner/email/telefon stehen hier
 * bewusst nicht mehr: Ansprechpartner sind jetzt eigene Datensätze im Reiter
 * "Kontakte", weil es fast nie bei einem bleibt. Die alten Spalten sind
 * weiterhin in der Datenbank und unverändert — sie wurden beim Umstieg in
 * die Kontakte kopiert, nicht verschoben. Wer sie braucht, findet sie dort.
 */
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

                Section::make('Betreuung')
                    ->description('Wo dieser Kunde in der Beziehung steht — und was mit uns vereinbart ist.')
                    ->columns(3)
                    ->schema([
                        Select::make('betreuung')
                            ->label('Stand')
                            ->options(Betreuung::class)
                            ->default(Betreuung::Aktiv->value)
                            ->required()
                            ->helperText('Rein intern. Der Kunde sieht das nirgends.'),

                        DatePicker::make('kunde_seit')
                            ->label('Kunde seit')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->maxDate(now()),

                        TextInput::make('vertragsart')
                            ->label('Vertrag')
                            ->maxLength(255)
                            ->datalist(['Einmaliges Projekt', 'Wartung', 'Betreuungspaket', 'Auf Zuruf'])
                            ->helperText('Freitext — die Liste ist nur ein Vorschlag.'),

                        DatePicker::make('vertrag_bis')
                            ->label('Läuft bis')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->helperText('Leer = unbefristet.'),

                        TextInput::make('kuendigungsfrist_tage')
                            ->label('Kündigungsfrist (Tage)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999)
                            ->suffix('Tage'),

                        Toggle::make('aktiv')
                            ->label('In Auswahllisten anbieten')
                            ->default(true)
                            ->helperText('Aus: der Kunde verschwindet aus Auswahllisten, seine Tickets und Zeiten bleiben.'),
                    ]),

                Section::make('Technik')
                    ->description('Wo das läuft. Die Adressen der einzelnen Projekte stehen am Projekt.')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->placeholder('https://…'),

                        TextInput::make('hoster')
                            ->label('Hoster')
                            ->maxLength(255)
                            ->datalist(['Strato', 'Hetzner', 'IONOS', 'All-Inkl', 'Eigener VPS'])
                            ->helperText('Bei wem man anruft, wenn der Server nicht antwortet.'),

                        Textarea::make('notizen')
                            ->label('Interne Notizen')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Sieht nur das Team. Zugangsdaten gehören in den Reiter "Zugangsdaten", nicht hierhin — dort sind sie verschlüsselt.'),
                    ]),

                Section::make('Rechnungsdaten')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('rechnung_email')
                            ->label('Rechnung an')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Geht oft an die Buchhaltung und nicht an den Ansprechpartner.'),

                        TextInput::make('ust_id')
                            ->label('USt-IdNr.')
                            ->maxLength(20)
                            ->placeholder('DE123456789'),

                        TextInput::make('strasse')
                            ->label('Straße und Hausnummer')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('plz')
                            ->label('PLZ')
                            ->maxLength(10),

                        TextInput::make('ort')
                            ->label('Ort')
                            ->maxLength(255),

                        TextInput::make('land')
                            ->label('Land')
                            ->maxLength(2)
                            ->default('DE')
                            ->helperText('Zwei Buchstaben: DE, AT, CH.'),
                    ]),

                Section::make('Zuständige Mitarbeiter')
                    ->description('Wer hier steht, sieht alle Projekte und Tickets dieses Kunden — auch künftige. Administratoren müssen nicht eingetragen werden.')
                    ->collapsed()
                    ->schema([
                        Select::make('mitarbeiter')
                            ->label('Mitarbeiter')
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
