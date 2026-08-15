<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Project;
use App\Support\Startpasswort;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Das Formular für einen Tresoreintrag.
 *
 * Einmal geschrieben und von beiden Stellen benutzt — beim Kunden und beim
 * Projekt. Zwei Fassungen wären zwei Orte, an denen der Schalter "für den
 * Kunden sichtbar" unterschiedlich vorbelegt sein kann, und das ist genau
 * der Fehler, den man hier nicht machen darf.
 */
class ZugangsdatenForm
{
    /**
     * @param  bool  $mitProjektauswahl  Beim Kunden ja (der Eintrag kann zu
     *                                   einem Projekt gehören), beim Projekt
     *                                   nein — dort steht es schon fest.
     */
    public static function configure(Schema $schema, bool $mitProjektauswahl = true): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('bezeichnung')
                    ->label('Wofür')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->datalist([
                        'WordPress-Admin',
                        'Basic-Auth der Vorschau',
                        'SFTP',
                        'Hoster-Konto',
                        'DNS-Verwaltung',
                        'Mailkonto',
                    ])
                    ->helperText('So steht es später in der Liste — und beim Kunden, falls freigegeben.'),

                ...($mitProjektauswahl ? [
                    Select::make('project_id')
                        ->label('Gehört zu Projekt')
                        ->relationship('project', 'name')
                        ->getOptionLabelFromRecordUsing(fn (Project $record) => $record->name)
                        ->searchable()
                        ->preload()
                        ->placeholder('Kein bestimmtes — gilt für den ganzen Kunden')
                        ->helperText('Zugänge zu einem Projekt erscheinen beim Kunden auf dessen Projektseite, alle anderen unter "Mein Konto".')
                        ->columnSpanFull(),
                ] : []),

                TextInput::make('url')
                    ->label('Adresse')
                    ->url()
                    ->maxLength(255)
                    ->prefixIcon('heroicon-o-globe-alt')
                    ->placeholder('https://…/wp-admin')
                    ->columnSpanFull()
                    ->helperText('Wo man sich damit anmeldet.'),

                TextInput::make('benutzername')
                    ->label('Benutzername')
                    ->maxLength(255)
                    ->autocomplete(false),

                TextInput::make('passwort')
                    ->label('Passwort')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    // Anders als bei einem Anmeldepasswort steht hier der
                    // echte Wert im Feld: der Tresor ist zum Nachschlagen da,
                    // nicht zum Anmelden. Deshalb verschlüsselt und nicht
                    // gehasht — ein Hash ließe sich nie wieder vorlesen.
                    ->helperText('Verschlüsselt gespeichert. Wird beim Bearbeiten wieder angezeigt — das ist der Zweck.')
                    ->suffixAction(
                        Action::make('erzeugen')
                            ->label('Vorschlag')
                            ->icon('heroicon-m-sparkles')
                            ->tooltip('Ein sprechbares Passwort vorschlagen')
                            ->action(fn ($set) => $set('passwort', Startpasswort::erzeugen())),
                    ),

                Textarea::make('hinweis')
                    ->label('Hinweis')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Was man sonst noch wissen muss: Zwei-Faktor, IP-Sperre, wer den Zugang eingerichtet hat.'),

                Toggle::make('kunden_sichtbar')
                    ->label('Der Kunde darf das sehen')
                    ->columnSpanFull()
                    // Kein ->default(true): der Vorgabewert steht am Model und
                    // ist "aus". Ein vergessener Schalter soll dazu führen,
                    // dass der Kunde etwas nicht sieht — nie umgekehrt.
                    ->helperText('Aus (Vorgabe): nur für uns. An: erscheint im Kundenbereich mit Kopierknopf. Unsere eigenen Server- und Hoster-Zugänge bleiben aus.'),

                TextInput::make('sortierung')
                    ->label('Reihenfolge')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(999)
                    ->columnSpanFull()
                    ->helperText('Kleinere Zahlen stehen oben.'),
            ]);
    }
}
