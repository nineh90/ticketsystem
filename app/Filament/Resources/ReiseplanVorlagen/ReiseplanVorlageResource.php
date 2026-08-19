<?php

namespace App\Filament\Resources\ReiseplanVorlagen;

use App\Filament\Resources\ReiseplanVorlagen\Pages\ListReiseplanVorlagen;
use App\Models\ReiseplanVorlage;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Reiseplan-Vorlagen — die Etappen, die ein Projekt mitbekommt.
 *
 * Steht im Maschinenraum, weil es eine Einstellung ist und keine Arbeit:
 * man kommt zwei- bis dreimal im Jahr her. Dass es die Seite überhaupt gibt,
 * ist der eigentliche Punkt — die Texte stehen wörtlich beim Kunden, und wer
 * sie ändern will, soll dafür keinen Entwickler brauchen.
 *
 * Die Etappen liegen als Repeater im selben Formular und nicht als eigener
 * Reiter: eine Vorlage hat drei bis neun davon, und man ändert sie im
 * Zusammenhang. Ein Reiter dafür hieße, sie einzeln aufzurufen, um zu sehen,
 * ob der Ton zusammenpasst.
 */
class ReiseplanVorlageResource extends Resource
{
    protected static ?string $model = ReiseplanVorlage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Reiseplan-Vorlagen';

    protected static ?string $modelLabel = 'Vorlage';

    protected static ?string $pluralModelLabel = 'Reiseplan-Vorlagen';

    protected static string|\UnitEnum|null $navigationGroup = 'Maschinenraum';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'reiseplan-vorlagen';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Name der Vorlage')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Website')
                    ->live(onBlur: true)
                    // Der Schlüssel entsteht aus dem Namen, aber nur beim
                    // Anlegen: er steht in gespeicherten Formularzuständen
                    // und darf sich nicht ändern, wenn jemand den Namen
                    // nachschärft.
                    ->afterStateUpdated(function (?string $state, callable $set, ?ReiseplanVorlage $record) {
                        if ($record === null) {
                            $set('schluessel', Str::slug((string) $state, dictionary: []));
                        }
                    })
                    ->helperText('Steht nur bei uns im Auswahlfeld — der Kunde sieht ihn nie.'),

                TextInput::make('schluessel')
                    ->label('Kürzel')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->alphaDash()
                    ->helperText('Bleibt, auch wenn der Name sich ändert. Nur Kleinbuchstaben und Bindestriche.'),

                Toggle::make('ist_vorgabe')
                    ->label('Im Formular vorausgewählt')
                    ->inline(false)
                    ->helperText('Es gibt genau eine. Schaltest du sie hier an, geht sie bei der anderen aus.'),

                Repeater::make('punkte')
                    ->label('Etappen')
                    ->relationship()
                    ->orderColumn('sortierung')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['titel'] ?? null)
                    ->addActionLabel('Etappe hinzufügen')
                    ->defaultItems(1)
                    ->schema([
                        TextInput::make('titel')
                            ->label('Etappe')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Erstgespräch')
                            ->helperText('Aus Sicht des Kunden formuliert — er liest es genau so.'),

                        Textarea::make('beschreibung')
                            ->label('Erklärung')
                            ->rows(3)
                            ->helperText('Der Satz darunter im Reiseplan. Auch der steht beim Kunden.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sortierung')
            ->reorderable('sortierung')
            ->columns([
                TextColumn::make('name')
                    ->label('Vorlage')
                    ->weight('medium')
                    ->searchable(),

                TextColumn::make('schluessel')
                    ->label('Kürzel')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('punkte_count')
                    ->label('Etappen')
                    ->counts('punkte')
                    ->alignEnd(),

                IconColumn::make('ist_vorgabe')
                    ->label('Vorausgewählt')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()->label('Löschen'),
            ])
            ->emptyStateHeading('Noch keine Vorlage')
            ->emptyStateDescription('Eine Vorlage sammelt die Etappen, die ein Projekt üblicherweise durchläuft.');
    }

    /** Vorlagen sind eine Einstellung — die ändert der Administrator. */
    public static function canAccess(): bool
    {
        return auth()->user()?->istAdmin() === true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReiseplanVorlagen::route('/'),
        ];
    }
}
