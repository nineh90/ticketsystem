<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Meilenstein;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Die Meilensteine eines Projekts — der Zeitstrahl, den der Kunde sieht.
 *
 * Die Liste ist von Hand sortierbar (reorderable), weil Meilensteine eine
 * Erzählung sind: "Entwurf steht" gehört vor "Inhalte eingepflegt", auch
 * wenn beim zweiten ein Datum steht und beim ersten nicht.
 */
class MeilensteineRelationManager extends RelationManager
{
    protected static string $relationship = 'meilensteine';

    protected static ?string $title = 'Meilensteine';

    protected static ?string $modelLabel = 'Meilenstein';

    protected static ?string $pluralModelLabel = 'Meilensteine';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-flag';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('titel')
                    ->label('Meilenstein')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->placeholder('Entwurf steht')
                    ->helperText('Aus Sicht des Kunden formuliert — er liest es genau so.'),

                Textarea::make('beschreibung')
                    ->label('Erklärung')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Optional. Ein Satz, was das bedeutet.'),

                DatePicker::make('faellig_am')
                    ->label('Geplant für')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->helperText('Leer lassen, wenn noch kein Termin steht — das ist ehrlicher als ein geratener.'),

                DatePicker::make('erledigt_at')
                    ->label('Erledigt am')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->maxDate(now())
                    ->helperText('Gesetzt = abgehakt. Bewegt den Fortschrittsbalken beim Kunden.'),

                Toggle::make('kunden_sichtbar')
                    ->label('Der Kunde sieht diesen Punkt')
                    ->default(true)
                    ->columnSpanFull()
                    ->helperText('An (Vorgabe). Aus für Schritte, die ihn nur beunruhigen — sie zählen dann auch nicht in den Fortschritt.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Woraus sich der Fortschrittsbalken im Kundenbereich rechnet: erledigte durch alle kundensichtbaren.')
            ->columns([
                TextColumn::make('titel')
                    ->label('Meilenstein')
                    ->weight('medium')
                    ->description(fn (Meilenstein $record) => $record->beschreibung
                        ? str($record->beschreibung)->squish()->limit(80)->toString()
                        : null)
                    ->searchable(),

                TextColumn::make('faellig_am')
                    ->label('Geplant')
                    ->date('d.m.Y')
                    ->placeholder('offen')
                    // Überfällig fällt intern auf, im Kundenbereich nicht —
                    // ein selbst gesetzter Termin, den wir reißen, ist eine
                    // Nachricht an uns.
                    ->color(fn (Meilenstein $record) => $record->istUeberfaellig() ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('erledigt_at')
                    ->label('Erledigt')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->color('success'),

                IconColumn::make('kunden_sichtbar')
                    ->label('Kunde sieht es')
                    ->boolean(),
            ])
            ->reorderable('sortierung')
            ->defaultSort('sortierung')
            ->headerActions([
                CreateAction::make()->label('Meilenstein anlegen'),
            ])
            ->recordActions([
                // Der häufigste Handgriff überhaupt: einen Punkt abhaken.
                // Dafür soll niemand ein Formular öffnen und ein Datum
                // eintippen, das immer "heute" lautet.
                Action::make('abhaken')
                    ->label(fn (Meilenstein $record) => $record->istErledigt() ? 'Wieder öffnen' : 'Abhaken')
                    ->icon(fn (Meilenstein $record) => $record->istErledigt() ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check')
                    ->color(fn (Meilenstein $record) => $record->istErledigt() ? 'gray' : 'success')
                    ->action(fn (Meilenstein $record) => $record->update([
                        'erledigt_at' => $record->istErledigt() ? null : now(),
                    ])),

                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()->label('Löschen'),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-flag')
            ->emptyStateHeading('Noch keine Meilensteine')
            ->emptyStateDescription('Drei bis sechs Punkte genügen. Sie beantworten die Frage „wie weit seid ihr?", bevor sie gestellt wird.');
    }
}
