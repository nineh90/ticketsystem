<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Customers\Schemas\ZugangsdatenForm;
use App\Models\Zugangsdaten;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Der Zugangsdaten-Tresor eines Kunden.
 *
 * Sichtbar für alle, die den Kunden sehen — also für die Mitarbeiter, die
 * seine Projekte betreuen. Das ist Absicht: wer an einer Seite arbeitet,
 * braucht den Login dazu, und ein Tresor, an den nur der Chef kommt, wird
 * binnen einer Woche durch eine Textdatei ersetzt.
 *
 * Die Passwörter stehen nicht in der Liste. Sie sind einen Klick entfernt
 * (Kopieren oder Bearbeiten) — das reicht im Alltag und verhindert, dass
 * beim Vorführen des Systems mit geteiltem Bildschirm eine ganze Spalte
 * Passwörter offen daliegt.
 */
class ZugangsdatenRelationManager extends RelationManager
{
    protected static string $relationship = 'zugangsdaten';

    protected static ?string $title = 'Zugangsdaten';

    protected static ?string $modelLabel = 'Zugang';

    protected static ?string $pluralModelLabel = 'Zugangsdaten';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-lock-closed';

    public function form(Schema $schema): Schema
    {
        return ZugangsdatenForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Verschlüsselt gespeichert. Der Schalter je Eintrag entscheidet, ob der Kunde ihn in seinem Bereich sieht — im Zweifel aus.')
            ->columns([
                TextColumn::make('bezeichnung')
                    ->label('Wofür')
                    ->weight('medium')
                    ->description(fn (Zugangsdaten $record) => $record->project?->name)
                    ->searchable(),

                TextColumn::make('benutzername')
                    ->label('Benutzer')
                    ->copyable()
                    ->copyMessage('Kopiert')
                    ->placeholder('—')
                    ->searchable(),

                // Der Wert wird nie angezeigt, nur kopiert. copyableState
                // liefert das entschlüsselte Passwort an die Zwischenablage,
                // während in der Spalte Punkte stehen.
                // Unlesbar heißt: der Geheimtext passt nicht zum APP_KEY
                // — etwa auf einer Kopie der Datenbank. Das muss man sehen
                // können, sonst sucht man den Fehler beim Passwort selbst.
                TextColumn::make('passwort')
                    ->label('Passwort')
                    ->state(fn (Zugangsdaten $record) => $record->passwortUnlesbar()
                        ? 'nicht lesbar'
                        : ($record->passwort === null ? null : '••••••••'))
                    ->color(fn (Zugangsdaten $record) => $record->passwortUnlesbar() ? 'danger' : null)
                    ->copyable(fn (Zugangsdaten $record) => ! $record->passwortUnlesbar())
                    ->copyableState(fn (Zugangsdaten $record) => $record->passwort)
                    ->copyMessage('Passwort kopiert')
                    ->tooltip(fn (Zugangsdaten $record) => $record->passwortUnlesbar()
                        ? 'Verschlüsselt mit einem anderen APP_KEY — hier nicht zu entschlüsseln'
                        : 'Klicken kopiert das Passwort')
                    ->placeholder('—'),

                TextColumn::make('url')
                    ->label('Adresse')
                    ->url(fn (Zugangsdaten $record) => $record->url)
                    ->openUrlInNewTab()
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('kunden_sichtbar')
                    ->label('Kunde sieht es')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    // Sichtbar ist hier nicht "gut", sondern die Ausnahme —
                    // deshalb kein grüner Haken, der zum Anklicken einlädt.
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->inReihenfolge())
            ->filters([
                TernaryFilter::make('kunden_sichtbar')
                    ->label('Für den Kunden sichtbar'),
            ])
            ->headerActions([
                CreateAction::make()->label('Zugang hinterlegen'),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()->label('Löschen'),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-lock-closed')
            ->emptyStateHeading('Noch keine Zugangsdaten')
            ->emptyStateDescription('WordPress, SFTP, Hoster, die Basic-Auth der Vorschau — was der Kunde davon sehen soll, entscheidet ein Schalter je Eintrag.');
    }
}
