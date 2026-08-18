<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\DokumentArt;
use App\Enums\DokumentStand;
use App\Filament\Resources\Customers\Schemas\DokumentForm;
use App\Models\Customer;
use App\Models\Dokument;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Angebote, Rechnungen und Verträge eines Kunden.
 *
 * Die PDF kommt aus sevDesk und wird hier abgelegt. Was daneben steht, ist
 * bewusst wenig: genug, um in der Liste zu sehen, was noch offen ist, ohne
 * ein einziges PDF zu öffnen.
 *
 * Die Spalte "Sichtbar" steht weit rechts und trotzdem in der Liste — sie
 * ist die Antwort auf die Frage, die man beim Blick auf einen Kunden am
 * ehesten hat: hat er das eigentlich schon?
 */
class DokumenteRelationManager extends RelationManager
{
    protected static string $relationship = 'dokumente';

    protected static ?string $title = 'Dokumente';

    protected static ?string $modelLabel = 'Dokument';

    protected static ?string $pluralModelLabel = 'Dokumente';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-currency-euro';

    /** Siehe CommentsRelationManager: sonst fehlen alle Knöpfe. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        /** @var Customer $kunde */
        $kunde = $this->getOwnerRecord();

        return DokumentForm::configure($schema, $kunde);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Fertige PDF aus sevDesk. Der Schalter je Dokument entscheidet, ob der Kunde es sieht — im Zweifel aus.')
            ->columns([
                TextColumn::make('art')
                    ->label('Art')
                    ->badge()
                    ->sortable(),

                TextColumn::make('titel')
                    ->label('Titel')
                    ->weight('medium')
                    ->searchable()
                    // Nummer und Projekt darunter statt in zwei eigenen
                    // Spalten: beide sind kurz, und eine Tabelle mit acht
                    // Spalten liest man auf einem Laptop nicht mehr.
                    ->description(fn (Dokument $record) => collect([
                        $record->nummer,
                        $record->project?->name,
                    ])->filter()->implode(' · ') ?: null),

                TextColumn::make('datum')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('betrag')
                    ->label('Betrag')
                    ->state(fn (Dokument $record) => $record->betragLesbar())
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('stand')
                    ->label('Stand')
                    ->badge()
                    ->placeholder('—')
                    // Überfällig ist kein eigener Stand, sondern ein offener
                    // mit abgelaufener Frist. Als Beschreibung darunter und
                    // nicht als eigene Spalte: es ist dieselbe Aussage.
                    ->description(fn (Dokument $record) => $record->istUeberfaellig()
                        ? 'seit '.$record->faellig_am->diffInDays(today()).' Tagen fällig'
                        : null)
                    ->color(fn (Dokument $record) => $record->istUeberfaellig()
                        ? 'danger'
                        : $record->stand?->getColor()),

                IconColumn::make('kunden_sichtbar')
                    ->label('Sichtbar')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Dokument $record) => $record->kunden_sichtbar
                        ? 'Der Kunde sieht dieses Dokument'
                        : 'Nur intern'),

                // Wann der Kunde geantwortet hat. Leer, wenn wir den Stand
                // selbst gesetzt haben — genau das ist die Aussage.
                TextColumn::make('beantwortet_at')
                    ->label('Antwort')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->description(fn (Dokument $record) => $record->beantwortetVon?->name)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('dateiname')
                    ->label('Datei')
                    ->description(fn (Dokument $record) => $record->groesseLesbar())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('datum', 'desc')
            ->filters([
                SelectFilter::make('art')
                    ->label('Art')
                    ->options(DokumentArt::class),

                SelectFilter::make('stand')
                    ->label('Stand')
                    ->options(DokumentStand::class),

                TernaryFilter::make('kunden_sichtbar')
                    ->label('Für den Kunden sichtbar')
                    ->placeholder('Alle')
                    ->trueLabel('Freigegeben')
                    ->falseLabel('Nur intern'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Dokument hochladen')
                    ->icon('heroicon-o-arrow-up-tray')
                    // Wie bei den Anhängen: aus dem abgelegten Pfad werden
                    // Anzeigename, Typ und Größe gelesen. Das Formular kennt
                    // nur das Feld "pfad" — alles andere steht danach fest
                    // und von Hand eingetragen wäre es dreimal dieselbe
                    // Angabe.
                    ->mutateDataUsing(fn (array $data) => self::dateiAngabenErgaenzen($data)),
            ])
            ->recordActions([
                Action::make('herunterladen')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Dokument $record) => $record->url())
                    ->openUrlInNewTab(),

                EditAction::make()
                    ->label('Bearbeiten')
                    ->mutateDataUsing(fn (array $data) => self::dateiAngabenErgaenzen($data)),

                DeleteAction::make()
                    ->label('Löschen')
                    // Der Hinweis steht hier und nicht nur im Modell: dass
                    // mit dem Datensatz auch die Datei geht, ist die Sorte
                    // Nebenwirkung, die man einmal zu spät bemerkt.
                    ->modalDescription('Der Eintrag und die PDF-Datei werden gelöscht. Das lässt sich nicht rückgängig machen.'),
            ])
            ->emptyStateHeading('Noch keine Dokumente')
            ->emptyStateDescription('Angebote, Rechnungen und Verträge aus sevDesk. Freigegebene erscheinen im Bereich des Kunden — solange keines freigegeben ist, sieht er den Bereich gar nicht.')
            ->emptyStateIcon('heroicon-o-document-currency-euro');
    }

    /**
     * Anzeigename, Typ und Größe aus der abgelegten Datei nachtragen.
     *
     * Beim Bearbeiten ohne neue Datei bleibt der Pfad derselbe; die Angaben
     * werden dann neu gelesen und stimmen wieder überein. Fehlt die Datei
     * (etwa weil sie von Hand entfernt wurde), bleibt es beim Bestand —
     * hier eine Ausnahme zu werfen hieße, dass sich der Datensatz nicht mehr
     * speichern lässt, obwohl man gerade den Titel korrigieren wollte.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function dateiAngabenErgaenzen(array $data): array
    {
        $pfad = $data['pfad'] ?? null;

        if (! is_string($pfad) || $pfad === '') {
            return $data;
        }

        $data['user_id'] ??= auth()->id();

        $platte = Storage::disk(Dokument::PLATTE);

        if (! $platte->exists($pfad)) {
            return $data;
        }

        $basis = basename($pfad);

        $data['dateiname'] = str_contains($basis, '__') ? Str::after($basis, '__') : $basis;
        $data['mime'] = $platte->mimeType($pfad) ?: null;
        $data['groesse'] = $platte->size($pfad);

        return $data;
    }
}
