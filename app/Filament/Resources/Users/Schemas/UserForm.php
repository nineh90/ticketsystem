<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\MailEreignis;
use App\Enums\Rolle;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    /**
     * Rolle aus dem Formularzustand lesen.
     *
     * Nötig, weil $get('rolle') je nach Lage etwas anderes liefert: beim
     * Anlegen den String aus ->default(), beim Bearbeiten den gecasteten
     * Enum-Fall aus dem Model. Ein schlichtes === gegen den String schlug
     * deshalb beim Bearbeiten immer fehl — mit der Folge, dass der Abschnitt
     * "Zuständigkeit" unsichtbar blieb und sich einem bestehenden Mitarbeiter
     * überhaupt kein Projekt zuweisen ließ.
     */
    private static function rolle(callable $get): ?Rolle
    {
        $wert = $get('rolle');

        return $wert instanceof Rolle ? $wert : Rolle::tryFrom((string) $wert);
    }

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
                            // Für Kundenzugänge Pflicht: ohne Kunde ist im
                            // Kundenbereich nicht bestimmbar, was die Person
                            // sehen darf — User::canAccessPanel lässt sie
                            // dann gar nicht erst hinein.
                            ->visible(fn ($get) => self::rolle($get) === Rolle::Kunde)
                            ->required(fn ($get) => self::rolle($get) === Rolle::Kunde)
                            ->helperText('Bestimmt, welche Projekte und Anliegen dieser Zugang sieht. Bequemer anzulegen ist ein Kundenzugang unter Kunden → der Kunde → Zugänge.'),

                        Toggle::make('panel_zugang')
                            ->label('Zugang freigegeben')
                            ->helperText('Erst hiermit kommt die Person ins Dashboard.'),

                        Toggle::make('aktiv')
                            ->label('Aktiv')
                            ->default(true)
                            ->helperText('Ausgeschiedene Personen deaktivieren statt löschen — ihre Tickets und Zeiten bleiben zuordenbar.'),

                        // Bewusst je Zugang und nicht für alle: der Versand
                        // wird stufenweise eingeführt. Bei Kundenzugängen
                        // bleibt er wirkungslos, solange ihre Adressen nicht
                        // bestätigt sind (User::bekommtMailMeldungen) —
                        // deshalb steht das Feld dort auch nicht.
                        Toggle::make('mail_benachrichtigungen')
                            ->label('E-Mail bei Meldungen')
                            ->visible(fn (?User $record) => ! ($record?->istKunde() ?? false))
                            // live, weil die Auswahl darunter daran hängt.
                            ->live()
                            ->helperText('Zusätzlich zur Glocke. Ohne hinterlegten Mailversand passiert nichts — dann steht die Mail nur im Protokoll.'),

                        // Worüber. Ohne diese Auswahl wäre der Versand alles
                        // oder nichts, und wer täglich fünf Mails bekommt,
                        // von denen ihn zwei angehen, übergeht nach einer
                        // Woche alle fünf.
                        CheckboxList::make('mail_ereignisse')
                            ->label('Und zwar worüber')
                            ->options(MailEreignis::class)
                            ->descriptions(collect(MailEreignis::cases())
                                ->mapWithKeys(fn (MailEreignis $e) => [$e->value => $e->getDescription()])
                                ->all())
                            ->columns(2)
                            ->columnSpanFull()
                            ->bulkToggleable()
                            // Vorgabe für einen neuen Zugang: alles, was
                            // hereinkommt. Über das, was wir selbst nach
                            // außen schicken, braucht niemand eine Mail — er
                            // war es meistens selbst.
                            ->default(MailEreignis::vorgabeIntern())
                            ->visible(fn (Get $get, ?User $record) => $get('mail_benachrichtigungen')
                                && ! ($record?->istKunde() ?? false))
                            ->helperText('Nichts angehakt heißt: keine Mail. Die letzten beiden gehen an Kunden und bleiben ohne Wirkung, solange Kundenzugänge keine Mail bekommen.'),
                    ]),

                Section::make('Zuständigkeit')
                    ->description('Mitarbeiter sehen ausschließlich, was hier zugeordnet ist. Beide Wege gelten nebeneinander — einer genügt. Für Administratoren ohne Bedeutung, sie sehen ohnehin alles.')
                    ->schema([
                        Select::make('customers')
                            ->label('Ganze Kunden')
                            ->relationship('customers', 'name', fn ($query) => $query->aktiv()->orderBy('name'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(
                                fn ($record) => $record->kuerzel.' — '.$record->name,
                            )
                            ->helperText('Schließt alle Projekte dieses Kunden ein, auch die, die erst später entstehen. Der bequemere Weg, wenn jemand einen Kunden komplett betreut.'),

                        Select::make('projects')
                            ->label('Einzelne Projekte')
                            ->relationship('projects', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            // Im Auswahlfeld den Kunden mitzeigen: zwei Kunden
                            // dürfen beide ein Projekt "Website" haben.
                            ->getOptionLabelFromRecordUsing(
                                fn ($record) => $record->customer->name.' — '.$record->name,
                            )
                            ->helperText('Nur diese Projekte. Bei einem neuen Projekt desselben Kunden muss hier nachgetragen werden.'),
                    ])
                    ->visible(fn ($get) => self::rolle($get) === Rolle::Mitarbeiter),
            ]);
    }
}
