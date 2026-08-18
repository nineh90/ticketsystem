<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\DokumentArt;
use App\Enums\DokumentStand;
use App\Filament\Resources\Customers\Schemas\DokumentForm;
use App\Models\Customer;
use App\Models\Dokument;
use App\Models\TimeEntry;
use App\Support\Abrechnung;
use App\Support\Dauer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
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

                // Wie viel Arbeitszeit diese Rechnung abdeckt. Leer bei
                // allem, dem nichts zugeordnet ist — also bei Angeboten und
                // bei Rechnungen, die noch niemand verknüpft hat.
                TextColumn::make('zeiten')
                    ->label('Zeiten')
                    ->state(fn (Dokument $record) => $record->timeEntries()->exists()
                        ? Dauer::alsStunden($record->zugeordneteMinuten())
                        : null)
                    ->placeholder('—')
                    ->alignEnd()
                    ->color('gray'),

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

                // Der Schritt, der die Zeiterfassung mit der Rechnung
                // verbindet. Nur an Rechnungen: ein Angebot deckt keine
                // geleistete Arbeit ab, es kündigt sie an.
                Action::make('zeiten')
                    ->label('Zeiten zuordnen')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->visible(fn (Dokument $record) => $record->art === DokumentArt::Rechnung)
                    ->modalHeading('Zeiten dieser Rechnung zuordnen')
                    ->modalDescription('Zugeordnete Buchungen gelten als abgerechnet und verschwinden aus der Abrechnungsliste.')
                    ->modalSubmitActionLabel('Zuordnen')
                    ->fillForm(fn (Dokument $record) => [
                        // Was schon zugeordnet ist, steht angehakt da —
                        // damit ist dasselbe Fenster auch der Weg, eine
                        // Zuordnung wieder zu lösen.
                        'zeiten' => $record->timeEntries()->pluck('id')->all(),
                    ])
                    ->schema(fn (Dokument $record) => [
                        CheckboxList::make('zeiten')
                            ->label('Offene Buchungen')
                            ->options(fn () => self::buchungsAuswahl($record))
                            ->bulkToggleable()
                            ->columns(1)
                            ->helperText('Nur abrechenbare, beendete Buchungen dieses Kunden. Zeiten ohne den Haken „abrechenbar" stehen hier nie.')
                            ->noSearchResultsMessage('Keine offenen Buchungen.'),
                    ])
                    ->action(function (Dokument $record, array $data) {
                        self::zuordnen($record, array_map('intval', $data['zeiten'] ?? []));
                    }),

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
     * Die Buchungen, die in dieser Rechnung stehen können.
     *
     * Offene dieses Kunden plus die, die dieser Rechnung bereits zugeordnet
     * sind — sonst verschwänden die schon zugeordneten aus der Auswahl und
     * das Fenster würde beim Öffnen alles wieder abwählen.
     *
     * @return array<int, string>
     */
    private static function buchungsAuswahl(Dokument $dokument): array
    {
        $nutzer = auth()->user();

        $offen = Abrechnung::buchungenFuer($dokument->customer, $nutzer)->get();

        $bereits = $dokument->timeEntries()->with(['ticket', 'user'])->get();

        return $offen
            ->concat($bereits)
            ->unique('id')
            ->sortBy('gestartet_am')
            ->mapWithKeys(fn (TimeEntry $zeit) => [
                $zeit->getKey() => sprintf(
                    '%s · %s · %s%s',
                    $zeit->gestartet_am->format('d.m.Y'),
                    Dauer::alsStunden((int) $zeit->minuten),
                    $zeit->ticket?->kennung() ?? '—',
                    $zeit->beschreibung ? ' — '.$zeit->beschreibung : '',
                ),
            ])
            ->all();
    }

    /**
     * Die Auswahl festschreiben.
     *
     * Zwei Schritte, und der erste ist der, den man vergisst: was abgewählt
     * wurde, muss wieder gelöst werden. Ohne ihn ließe sich eine Zuordnung
     * nie zurücknehmen, und ein Fehlgriff bliebe für immer stehen.
     *
     * Die Menge ist doppelt begrenzt — auf die Buchungen dieses Dokuments und
     * auf das, was der Nutzer überhaupt sehen darf. Ohne die zweite Grenze
     * ließe sich über eine nachgebaute Anfrage eine fremde Buchung an die
     * eigene Rechnung hängen.
     *
     * @param  array<int, int>  $ids
     */
    private static function zuordnen(Dokument $dokument, array $ids): void
    {
        $erlaubt = array_keys(self::buchungsAuswahl($dokument));
        $ids = array_values(array_intersect($ids, $erlaubt));

        // Erst lösen, was nicht mehr angehakt ist.
        $dokument->timeEntries()
            ->whereNotIn('id', $ids ?: [0])
            ->update(['dokument_id' => null]);

        if ($ids !== []) {
            TimeEntry::query()
                ->whereIn('id', $ids)
                ->update(['dokument_id' => $dokument->getKey()]);
        }
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
