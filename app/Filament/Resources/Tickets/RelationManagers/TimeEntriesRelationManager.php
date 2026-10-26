<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Filament\Concerns\StopptLaufendeZeiten;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Support\Dauer;
use App\Support\LaufendeZeiten;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TimeEntriesRelationManager extends RelationManager
{
    use StopptLaufendeZeiten;

    protected static string $relationship = 'timeEntries';

    // "Logbuch" und nicht "Zeiten": an Bord steht darin, wer wann wie
    // lange was gemacht hat. Das ist keine Metapher, das ist die Sache
    // selbst — und es beschreibt sie besser als "Zeiterfassung".
    //
    // Kunden sehen davon nichts (TimeEntry::sichtbarFuer gibt ihnen
    // 1 = 0), das Wort bleibt also unter uns.
    protected static ?string $title = 'Logbuch';

    /** Die Knöpfe unter "Zeit buchen": Minuten => Beschriftung. */
    private const DAUER_VORSCHLAEGE = [
        15 => '15 Min.',
        30 => '30 Min.',
        45 => '45 Min.',
        60 => '1 Std.',
        90 => '1½ Std.',
        120 => '2 Std.',
    ];

    /** Siehe AnhaengeRelationManager: sonst fehlen Start, Stopp und Nachtrag. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DateTimePicker::make('gestartet_am')
                    ->label('Von')
                    ->required()
                    ->seconds(false)
                    ->displayFormat('d.m.Y H:i')
                    ->default(now()),

                DateTimePicker::make('beendet_am')
                    ->label('Bis')
                    ->seconds(false)
                    ->displayFormat('d.m.Y H:i')
                    ->after('gestartet_am')
                    ->live()
                    // Minuten aus der Zeitspanne vorbelegen. Das Feld bleibt
                    // trotzdem änderbar: bei einem Nachtrag stimmen die
                    // Zeitstempel oft nicht mit der tatsächlichen Dauer
                    // überein, und dann zählt die Dauer.
                    ->afterStateUpdated(function ($state, $get, $set) {
                        if (blank($state) || blank($get('gestartet_am'))) {
                            return;
                        }

                        $von = Carbon::parse($get('gestartet_am'));
                        $bis = Carbon::parse($state);

                        if ($bis->greaterThan($von)) {
                            $set('minuten', (int) $von->diffInMinutes($bis));
                        }
                    }),

                TextInput::make('minuten')
                    ->label('Dauer (Minuten)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->helperText('Wird aus Von/Bis vorbelegt, lässt sich aber überschreiben.'),

                Toggle::make('abrechenbar')
                    ->label('Abrechenbar')
                    ->default(true),

                TextInput::make('beschreibung')
                    ->label('Tätigkeit')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Über der Tabelle: alle Uhren, die gerade laufen — auch die an
            // anderen Tickets. Wer hier steht, hat mit Zeit zu tun, und genau
            // hier soll auffallen, dass nebenan noch etwas mitläuft. Ohne das
            // sieht man die eigene laufende Uhr nur an dem einen Ticket, an
            // dem man sie gestartet hat.
            ->header(function () {
                $zeiten = LaufendeZeiten::fuer();

                if ($zeiten->isEmpty()) {
                    return null;
                }

                return view('filament.zeiten-kopf', ['zeiten' => $zeiten]);
            })
            ->columns([
                TextColumn::make('user.name')
                    ->label('Wer'),

                TextColumn::make('gestartet_am')
                    ->label('Von')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('dauer')
                    ->label('Dauer')
                    ->alignEnd()
                    ->state(fn (TimeEntry $record) => $record->laeuft()
                        ? 'läuft …'
                        : Dauer::alsStunden($record->minuten))
                    ->badge()
                    ->color(fn (TimeEntry $record) => $record->laeuft() ? 'warning' : 'gray'),

                TextColumn::make('beschreibung')
                    ->label('Tätigkeit')
                    ->placeholder('—')
                    ->wrap(),

                IconColumn::make('abrechenbar')
                    ->label('Abrechenbar')
                    ->boolean(),
            ])
            ->defaultSort('gestartet_am', 'desc')
            ->headerActions([
                Action::make('starten')
                    ->label('Uhr starten')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    // Ausgeblendet nur dann, wenn die eigene Uhr schon an
                    // genau diesem Ticket läuft — da wäre "starten" ein Knopf
                    // ohne Wirkung. Läuft sie woanders, bleibt der Knopf da
                    // und fragt nach (siehe unten). Vorher verschwand er
                    // wortlos, und wer wechseln wollte, suchte den Fehler bei
                    // sich statt beim Timer, der noch am alten Ticket hing.
                    ->visible(fn () => $this->laufendeZeit()?->ticket_id !== $this->getOwnerRecord()->getKey())
                    // Zwei parallel laufende Uhren derselben Person wären
                    // nicht auflösbar — niemand arbeitet an zwei Tickets
                    // gleichzeitig. Statt das zu verbieten, wird die alte
                    // Uhr auf Nachfrage sauber beendet.
                    //
                    // Das ->modal() davor ist der eigentliche Schalter und
                    // muss stehenbleiben: Filament öffnet ein Modal schon
                    // dann, wenn irgendein Modal-Teil gesetzt ist — und
                    // ->modalHeading() gilt für die Aktion, nicht nur für den
                    // Fall mit laufender Uhr. Ohne diese Zeile fragte der
                    // Knopf "Es läuft schon eine Uhr" auch dann, wenn gar
                    // keine lief.
                    ->modal(fn () => $this->laufendeZeit() !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Es läuft schon eine Uhr')
                    ->modalDescription(function (): ?string {
                        $laufend = $this->laufendeZeit();

                        if ($laufend === null) {
                            return null;
                        }

                        return 'An '.$laufend->ticket?->kennung().' — '.$laufend->ticket?->titel
                            .' läuft deine Uhr seit '.$laufend->gestartet_am->format('H:i').' Uhr, '
                            .'inzwischen '.Dauer::alsStunden($laufend->bisherigeMinuten()).'. '
                            .'Diese Buchung wird beendet und hier eine neue gestartet.';
                    })
                    ->modalSubmitActionLabel('Beenden und hier starten')
                    ->modalIcon('heroicon-o-clock')
                    ->action(function () {
                        // Erst die alte beenden, dann die neue starten: in der
                        // Gegenreihenfolge liefen für einen Augenblick zwei
                        // Uhren, und laufendeZeit() hätte die falsche erwischt.
                        $vorher = $this->laufendeZeit();
                        $vorher?->stoppen();

                        /** @var Ticket $ticket */
                        $ticket = $this->getOwnerRecord();

                        $stadiumVorher = $ticket->ticket_status_id;

                        TimeEntry::create([
                            'ticket_id' => $ticket->getKey(),
                            'user_id' => auth()->id(),
                            'gestartet_am' => now(),
                        ]);

                        // Das Ticket ist inzwischen auf "In Arbeit" gerutscht
                        // (TimeEntryObserver). Die Meldung sagt es dazu: eine
                        // Automatik, die sich nicht zeigt, hält man beim
                        // ersten Mal für einen Fehler — und beim zweiten für
                        // Zauberei.
                        $ticket->refresh();

                        Notification::make()
                            ->title('Die Uhr läuft')
                            ->body(implode(' ', array_filter([
                                $vorher === null
                                    ? null
                                    : $vorher->ticket?->kennung().' beendet mit '
                                        .Dauer::alsStunden($vorher->minuten).'.',
                                $ticket->ticket_status_id === $stadiumVorher
                                    ? null
                                    : $ticket->kennung().' steht jetzt auf '.$ticket->status?->name.'.',
                            ])) ?: null)
                            ->success()
                            ->send();
                    }),

                Action::make('stoppen')
                    ->label('Uhr stoppen')
                    ->icon('heroicon-o-stop')
                    ->color('warning')
                    ->visible(fn () => $this->laufendeZeit() !== null)
                    ->action(function () {
                        $laufend = $this->laufendeZeit();

                        if ($laufend === null) {
                            return;
                        }

                        $laufend->stoppen();

                        Notification::make()
                            ->title('Zeit erfasst: '.Dauer::alsStunden($laufend->minuten))
                            // Hinweis, falls die laufende Uhr an einem anderen
                            // Ticket hing — sonst wundert man sich, warum hier
                            // nichts erscheint.
                            ->body($laufend->ticket_id === $this->getOwnerRecord()->getKey()
                                ? null
                                : 'Die Buchung gehörte zu '.$laufend->ticket->kennung().'.')
                            ->success()
                            ->send();
                    }),

                // Der kurze Weg (NID-34): "eine halbe Stunde dran gesessen",
                // ohne Von und Bis auszurechnen. Gebucht wird ab dem Moment
                // des Klicks — für die Abrechnung zählen die Minuten, und der
                // Tag stimmt damit von selbst. Wer die genaue Uhrzeit
                // braucht, nimmt "Nachtragen".
                CreateAction::make('buchen')
                    ->label('Zeit buchen')
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->modalHeading('Zeit buchen')
                    ->modalSubmitActionLabel('Buchen')
                    ->createAnother(false)
                    ->schema([
                        ToggleButtons::make('vorschlag')
                            ->label('Wie lange?')
                            ->options(self::DAUER_VORSCHLAEGE)
                            ->inline()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(fn ($state, $set) => $set('minuten', $state)),

                        TextInput::make('minuten')
                            ->label('Minuten')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->maxValue(24 * 60)
                            ->suffix('Min.')
                            ->live(onBlur: true)
                            // Wer selbst tippt, hat keinen der Knöpfe gemeint —
                            // sonst stünde "30 Min." markiert neben einer 50.
                            ->afterStateUpdated(fn ($state, $set) => $set(
                                'vorschlag',
                                array_key_exists((int) $state, self::DAUER_VORSCHLAEGE) ? (int) $state : null,
                            ))
                            ->helperText('Einen Knopf wählen oder die Minuten selbst eintragen.'),

                        TextInput::make('beschreibung')
                            ->label('Tätigkeit')
                            ->maxLength(255),

                        Toggle::make('abrechenbar')
                            ->label('Abrechenbar')
                            ->default(true),
                    ])
                    ->mutateDataUsing(function (array $data): array {
                        $start = now()->startOfMinute();

                        return [
                            ...$data,
                            'user_id' => auth()->id(),
                            'gestartet_am' => $start,
                            'beendet_am' => $start->copy()->addMinutes((int) $data['minuten']),
                        ];
                    })
                    ->successNotificationTitle(fn (TimeEntry $record) => 'Gebucht: '.Dauer::alsStunden($record->minuten)),

                CreateAction::make()
                    ->label('Nachtragen')
                    ->icon('heroicon-o-plus')
                    ->color('gray')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()->label('Löschen'),
            ])
            ->emptyStateHeading('Noch keine Zeiten')
            ->emptyStateDescription('Starte die Uhr oder buche, wie lange du dran warst.');
    }

    /**
     * Die eigene laufende Buchung, egal an welchem Ticket.
     *
     * Bewusst ohne Merker: die Aktionen fragen mehrfach je Aufbau, und nach
     * einem Start oder Stopp zeichnet Livewire in derselben Anfrage neu. Ein
     * gemerkter Wert wäre dann der von vorhin, und der Knopf zeigte den
     * Zustand von vor dem Klick.
     */
    protected function laufendeZeit(): ?TimeEntry
    {
        return auth()->user()?->laufendeZeit();
    }
}
