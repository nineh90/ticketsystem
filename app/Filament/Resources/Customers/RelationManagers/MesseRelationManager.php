<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\Rolle;
use App\Models\Project;
use App\Models\Treffen;
use App\Models\User;
use App\Support\Messe;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
            ->components([
                TextInput::make('titel')
                    ->label('Worum geht es?')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->placeholder('Quartalsgespräch')
                    ->helperText('Aus Sicht des Kunden formuliert — er liest es genau so.'),

                DateTimePicker::make('beginnt_am')
                    ->label('Wann')
                    ->required()
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat('d.m.Y H:i')
                    ->minutesStep(15),

                TextInput::make('dauer_minuten')
                    ->label('Dauer')
                    ->numeric()
                    ->required()
                    ->default(30)
                    ->minValue(5)
                    ->maxValue(480)
                    ->suffix('Minuten'),

                TextInput::make('url')
                    ->label('Wo')
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->placeholder('https://meet.google.com/…')
                    // Ausdrücklich irgendein Link und nicht "Google Meet":
                    // was hinter dem Knopf liegt, soll austauschbar sein,
                    // ohne dass hier ein Feld umbenannt werden muss.
                    ->helperText('Der Link zur Besprechung. Dorthin führt beim Kunden der Knopf "An Bord gehen".'),

                Select::make('project_id')
                    ->label('Projekt')
                    ->options(fn () => Project::query()
                        ->where('customer_id', $this->getOwnerRecord()->getKey())
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Kein bestimmtes')
                    ->helperText('Optional. Das Quartalsgespräch gehört zum Kunden, die Abnahme zum Projekt.'),

                Select::make('crew_ids')
                    ->label('Wer von uns dabei ist')
                    ->multiple()
                    ->options(fn () => User::query()
                        ->where('aktiv', true)
                        ->where('rolle', '!=', Rolle::Kunde->value)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->default([auth()->id()])
                    ->columnSpanFull()
                    // Wer dazukommt, bekommt eine Meldung — und Mail, falls
                    // er "Meine Treffen" angehakt hat. Wer sich selbst
                    // einträgt, nicht: er füllt gerade das Formular aus.
                    ->helperText('Sie bekommen den Termin an die Glocke und stehen unter "Meine Wache".'),

                Toggle::make('kunden_sichtbar')
                    ->label('Einladen')
                    ->inline(false)
                    // Der Schalter IST die Einladung: springt er an, geht die
                    // Meldung hinaus (TreffenObserver). Deshalb hier auch die
                    // Vorgabe "aus" — ein Termin entsteht beim Planen, und
                    // ein Bleistiftstrich lädt niemanden ein.
                    ->helperText('Erst damit steht das Treffen beim Kunden — und er bekommt eine Meldung.'),

                Textarea::make('notiz')
                    ->label('Tagesordnung')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Optional. Sieht der Kunde ebenfalls — also nichts Internes hier hinein.'),
            ]);
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
                    ->mutateDataUsing(function (array $daten): array {
                        $daten['erstellt_von'] = auth()->id();

                        return $daten;
                    })
                    // Die Crew wird nach dem Anlegen gesetzt und nicht über
                    // Filaments ->relationship(): nur so weiß Messe, WER neu
                    // dazugekommen ist, und meldet sich nur bei denen.
                    ->using(function (array $daten, MesseRelationManager $livewire): Treffen {
                        $crew = Arr::pull($daten, 'crew_ids', []);

                        /** @var Treffen $treffen */
                        $treffen = $livewire->getRelationship()->create($daten);

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

                Action::make('kalender')
                    ->label('Kalender')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->url(fn (Treffen $record) => route('treffen.kalender', $record)),

                EditAction::make()
                    ->label('Ändern')
                    ->mutateRecordDataUsing(function (array $daten, Treffen $record): array {
                        $daten['crew_ids'] = $record->crew()->pluck('users.id')->all();

                        return $daten;
                    })
                    ->using(function (Treffen $record, array $daten): Treffen {
                        $crew = Arr::pull($daten, 'crew_ids', []);

                        $record->update($daten);

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
