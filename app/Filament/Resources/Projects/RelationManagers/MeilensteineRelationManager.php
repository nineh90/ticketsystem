<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Meilenstein;
use App\Models\Project;
use App\Support\MeilensteinVorlagen;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
            // Zweites Kriterium, sonst ist die Reihenfolge bei Gleichstand
            // dem Zufall überlassen: Postgres gibt Zeilen mit demselben Wert
            // in beliebiger Folge zurück, und die Liste sieht bei jedem Laden
            // anders aus. Betrifft alles, was vor der ersten Sortierung von
            // Hand angelegt wurde.
            ->defaultSort(fn ($query) => $query->orderBy('sortierung')->orderBy('id'))
            // Der Auslöser ist von Haus aus ein Knopf mit bloßem Pfeil-Symbol
            // und wird schlicht übersehen — man sucht die Sortierung dann in
            // einem Feld im Formular, das es nicht gibt.
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering) => $action
                ->button()
                ->label($isReordering ? 'Reihenfolge fertig' : 'Reihenfolge ändern')
                ->color($isReordering ? 'primary' : 'gray'))
            ->headerActions([
                $this->vorlageAnwenden(),
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
            ->emptyStateDescription('Drei bis sechs Punkte genügen. Sie beantworten die Frage „wie weit seid ihr?", bevor sie gestellt wird.')
            ->emptyStateActions([
                $this->vorlageAnwenden()->button()->color('primary'),
                CreateAction::make()->label('Einzeln anlegen')->button()->color('gray'),
            ]);
    }

    /**
     * Den üblichen Satz Meilensteine anhängen, statt ihn abzutippen.
     *
     * Bewusst ein Knopf und kein Automatismus beim Anlegen eines Projekts.
     * Ein Zeitstrahl, der ungefragt sieben Punkte hat, wird nicht gepflegt,
     * sondern weggeklickt — und beim Kunden steht dann eine Erzählung, die
     * mit seinem Projekt nichts zu tun hat.
     *
     * Angehängt wird ans Ende und in der Reihenfolge der Vorlage, damit der
     * Knopf auch bei einem Projekt taugt, an dem schon gearbeitet wurde: die
     * drei Punkte von Hand bleiben vorn, der Rest kommt dahinter.
     */
    protected function vorlageAnwenden(): Action
    {
        return Action::make('ausVorlage')
            ->label('Aus Vorlage')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('gray')
            ->modalHeading('Meilensteine aus einer Vorlage anlegen')
            ->modalDescription('Ausgewählte Punkte werden hinten angehängt. Nichts wird ersetzt oder gelöscht — was schon dasteht, bleibt.')
            ->modalSubmitActionLabel('Anlegen')
            ->schema([
                Select::make('vorlage')
                    ->label('Vorlage')
                    ->options(MeilensteinVorlagen::auswahl())
                    ->default(MeilensteinVorlagen::vorgabe())
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    // Beim Wechsel die Auswahl neu vorschlagen — sonst stehen
                    // dort noch die Titel der vorigen Vorlage, die es in der
                    // neuen gar nicht gibt.
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('punkte', $this->nochNichtVorhanden($state))),

                CheckboxList::make('punkte')
                    ->label('Diese Punkte anlegen')
                    ->options(fn (Get $get): array => MeilensteinVorlagen::punkte($get('vorlage'))
                        ->mapWithKeys(fn (array $punkt): array => [
                            $punkt['titel'] => $punkt['titel'].(
                                MeilensteinVorlagen::stehtSchonDa($this->projekt(), $punkt['titel'])
                                    ? '  ·  steht schon da'
                                    : ''
                            ),
                        ])
                        ->all())
                    ->descriptions(fn (Get $get): array => MeilensteinVorlagen::punkte($get('vorlage'))
                        ->mapWithKeys(fn (array $punkt): array => [$punkt['titel'] => $punkt['beschreibung'] ?? ''])
                        ->all())
                    ->default(fn (Get $get): array => $this->nochNichtVorhanden($get('vorlage')))
                    ->bulkToggleable()
                    ->required()
                    ->helperText('Vorausgewählt ist, was hier noch fehlt. Alles lässt sich danach umbenennen, ergänzen und ziehen.'),
            ])
            ->action(function (array $data): void {
                $punkte = MeilensteinVorlagen::punkte($data['vorlage'] ?? null)
                    ->filter(fn (array $punkt): bool => in_array($punkt['titel'], $data['punkte'] ?? [], strict: true));

                foreach ($punkte as $punkt) {
                    $this->projekt()->meilensteine()->create([
                        'titel' => $punkt['titel'],
                        'beschreibung' => $punkt['beschreibung'],
                    ]);
                }

                Notification::make()
                    ->success()
                    ->title($punkte->count() === 1 ? 'Ein Meilenstein angelegt' : $punkte->count().' Meilensteine angelegt')
                    ->body('Termine und Erledigt-Haken trägst du jetzt ein, die Reihenfolge lässt sich ziehen.')
                    ->send();
            });
    }

    /**
     * Die Titel der Vorlage, die bei diesem Projekt noch fehlen — der
     * Vorschlag, mit dem die Auswahl aufgeht.
     *
     * @return list<string>
     */
    protected function nochNichtVorhanden(?string $vorlage): array
    {
        return MeilensteinVorlagen::punkte($vorlage)
            ->reject(fn (array $punkt): bool => MeilensteinVorlagen::stehtSchonDa($this->projekt(), $punkt['titel']))
            ->pluck('titel')
            ->all();
    }

    protected function projekt(): Project
    {
        /** @var Project */
        return $this->getOwnerRecord();
    }
}
