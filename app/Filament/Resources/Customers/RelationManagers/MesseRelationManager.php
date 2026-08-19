<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Formulare\Treffenformular;
use App\Models\Treffen;
use App\Support\Kalender;
use App\Support\Messe;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

/**
 * Die Messe — Treffen mit diesem Kunden.
 *
 * An Bord ist die Messe der Raum, in dem man zusammenkommt. Hier steht, wann
 * das als Nächstes ist und wo: die Videokonferenz bleibt bei Google Meet
 * oder wo auch immer, die Adresse dorthin steht am Treffen.
 *
 * Der Termin lebte bisher in einer Mail und in zwei Kalendern. Das reicht,
 * solange beide Seiten die Mail wiederfinden — und genau daran hakt es jedes
 * Mal.
 */
class MesseRelationManager extends RelationManager
{
    protected static string $relationship = 'treffen';

    protected static ?string $title = 'Messe';

    protected static ?string $modelLabel = 'Treffen';

    protected static ?string $pluralModelLabel = 'Treffen';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-video-camera';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            // Dieselben Felder wie auf der Messe-Seite, nur ohne Kundenwahl:
            // hier steht der Kunde über die Beziehung schon fest.
            ->components(Treffenformular::felder(
                mitKundenwahl: false,
                customerId: $this->getOwnerRecord()->getKey(),
            ));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('titel')
            // Das nächste zuerst. Ein Kalender, der mit dem ältesten Termin
            // aufmacht, beantwortet die falsche Frage.
            ->defaultSort('beginnt_am', 'desc')
            ->columns([
                TextColumn::make('beginnt_am')
                    ->label('Wann')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->description(fn (Treffen $record) => $record->istAbgesagt()
                        ? 'abgesagt'
                        : $record->beginnt_am->diffForHumans())
                    ->color(fn (Treffen $record) => match (true) {
                        $record->istAbgesagt() => 'gray',
                        $record->laeuft() => 'success',
                        $record->istVorbei() => 'gray',
                        default => null,
                    }),

                TextColumn::make('titel')
                    ->label('Worum')
                    ->weight('medium')
                    ->description(fn (Treffen $record) => $record->project?->name)
                    ->wrap(),

                TextColumn::make('dauer_minuten')
                    ->label('Dauer')
                    ->suffix(' Min.')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('crew.name')
                    ->label('Dabei')
                    ->badge()
                    ->separator(',')
                    ->placeholder('niemand'),

                IconColumn::make('kunden_sichtbar')
                    ->label('Eingeladen')
                    ->boolean(),

                TextColumn::make('erstellerIn.name')
                    ->label('Angelegt von')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Treffen ansetzen')
                    ->mutateDataUsing(function (array $data): array {
                        $data['erstellt_von'] = auth()->id();

                        return $data;
                    })
                    // Die Crew wird nach dem Anlegen gesetzt und nicht über
                    // Filaments ->relationship(): nur so weiß Messe, WER neu
                    // dazugekommen ist, und meldet sich nur bei denen.
                    ->using(function (array $data, MesseRelationManager $livewire): Treffen {
                        $crew = Arr::pull($data, 'crew_ids', []);

                        /** @var Treffen $treffen */
                        $treffen = $livewire->getRelationship()->create($data);

                        Messe::crewSetzen($treffen, $crew);

                        return $treffen;
                    }),
            ])
            ->recordActions([
                Action::make('anBord')
                    ->label('An Bord')
                    ->icon('heroicon-o-video-camera')
                    ->color('success')
                    ->url(fn (Treffen $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn (Treffen $record) => filled($record->url) && ! $record->istAbgesagt()),

                // Google zuerst: wir haben unsere Kalender dort, und ein
                // Klick schlaegt eine heruntergeladene Datei, die man erst
                // noch oeffnen muss.
                Action::make('googleKalender')
                    ->label('Kalender')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->url(fn (Treffen $record) => Kalender::googleUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Treffen $record) => ! $record->istAbgesagt()),

                // Die Datei bleibt daneben — sie traegt eine Kennung und
                // ersetzt beim Verschieben den alten Eintrag, was der
                // Google-Link nicht kann.
                Action::make('kalenderDatei')
                    ->label('.ics')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Treffen $record) => route('treffen.kalender', $record)),

                EditAction::make()
                    ->label('Ändern')
                    ->mutateRecordDataUsing(function (array $data, Treffen $record): array {
                        $data['crew_ids'] = $record->crew()->pluck('users.id')->all();

                        return $data;
                    })
                    ->using(function (Treffen $record, array $data): Treffen {
                        $crew = Arr::pull($data, 'crew_ids', []);

                        $record->update($data);

                        Messe::crewSetzen($record, $crew);

                        return $record;
                    }),

                /*
                 * Absagen statt löschen.
                 *
                 * Ein gelöschtes Treffen verschwindet wortlos aus dem Bereich
                 * des Kunden — und er sitzt um zwei Uhr trotzdem davor. So
                 * bleibt es stehen, ist durchgestrichen, und er bekommt
                 * darüber eine Meldung.
                 */
                Action::make('absagen')
                    ->label('Absagen')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Treffen absagen')
                    ->modalDescription('Der Kunde bekommt eine Meldung. Der Termin bleibt bei ihm stehen, durchgestrichen.')
                    ->modalSubmitActionLabel('Absagen')
                    ->visible(fn (Treffen $record) => ! $record->istAbgesagt() && ! $record->istVorbei())
                    ->action(function (Treffen $record): void {
                        $record->update(['abgesagt_at' => now()]);

                        Notification::make()
                            ->title('Treffen abgesagt')
                            ->success()
                            ->send();
                    }),

                Action::make('wiederAnsetzen')
                    ->label('Doch wieder')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (Treffen $record) => $record->istAbgesagt())
                    ->action(fn (Treffen $record) => $record->update(['abgesagt_at' => null])),

                DeleteAction::make()->label('Löschen'),
            ])
            ->emptyStateHeading('Noch kein Treffen')
            ->emptyStateDescription('Setze eins an — der Kunde sieht es dann auf seiner Übersicht, mit Link und Kalendereintrag.')
            ->emptyStateIcon('heroicon-o-video-camera');
    }
}
