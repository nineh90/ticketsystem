<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\Kontakt;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Die Ansprechpartner beim Kunden.
 *
 * Getrennt von den Zugängen daneben: hier stehen Menschen, dort
 * Anmeldekonten. Der Buchhalter, an den die Rechnung geht, gehört hierhin
 * und bekommt kein Passwort.
 */
class KontakteRelationManager extends RelationManager
{
    protected static string $relationship = 'kontakte';

    protected static ?string $title = 'Kontakte';

    protected static ?string $modelLabel = 'Kontakt';

    protected static ?string $pluralModelLabel = 'Kontakte';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-user-circle';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('funktion')
                    ->label('Funktion')
                    ->maxLength(255)
                    ->datalist(['Geschäftsführung', 'Vorstand', 'Buchhaltung', 'Technik', 'Redaktion'])
                    ->helperText('Warum man diesen und keinen anderen anruft.'),

                TextInput::make('email')
                    ->label('E-Mail')
                    ->email()
                    ->maxLength(255),

                TextInput::make('telefon')
                    ->label('Telefon')
                    ->tel()
                    ->maxLength(255),

                Textarea::make('notiz')
                    ->label('Notiz')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Nur intern. "Erreichbar erst ab 14 Uhr", "erledigt die Freigaben".'),

                Toggle::make('hauptkontakt')
                    ->label('Hauptkontakt')
                    ->helperText('Der Name, der in Übersichten steht, wenn nur einer Platz hat.'),

                Toggle::make('aktiv')
                    ->label('Aktiv')
                    ->default(true)
                    ->helperText('Ausgeschieden? Deaktivieren statt löschen — sonst verlieren alte Absprachen ihren Namen.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->weight('medium')
                    ->description(fn (Kontakt $record) => $record->funktion)
                    ->searchable(),

                TextColumn::make('email')
                    ->label('E-Mail')
                    ->copyable()
                    ->copyMessage('Kopiert')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('telefon')
                    ->label('Telefon')
                    ->copyable()
                    ->copyMessage('Kopiert')
                    ->placeholder('—'),

                IconColumn::make('hauptkontakt')
                    ->label('Haupt')
                    ->boolean(),

                // Zeigt, wer von diesen Menschen sich auch anmelden kann.
                // Ohne die Spalte legt man beim zweiten Ansprechpartner einen
                // Zugang an, den es längst gibt.
                IconColumn::make('zugang')
                    ->label('Zugang')
                    ->state(fn (Kontakt $record) => $record->zugang()->exists())
                    ->boolean()
                    ->trueIcon('heroicon-o-key')
                    ->falseIcon('heroicon-o-minus-small')
                    ->falseColor('gray')
                    ->tooltip('Ob diese Person einen Kundenzugang hat'),

                IconColumn::make('aktiv')
                    ->label('Aktiv')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->inReihenfolge())
            ->headerActions([
                CreateAction::make()->label('Kontakt anlegen'),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()->label('Löschen'),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-user-circle')
            ->emptyStateHeading('Noch keine Ansprechpartner')
            ->emptyStateDescription('Wer ruft an, wer gibt frei, an wen geht die Rechnung — hier steht es.');
    }
}
