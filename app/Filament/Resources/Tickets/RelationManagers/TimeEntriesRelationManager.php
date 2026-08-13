<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Models\TimeEntry;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';

    protected static ?string $title = 'Zeiten';

    /** Siehe CommentsRelationManager: sonst fehlen Start, Stopp und Nachtrag. */
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

                        $von = \Illuminate\Support\Carbon::parse($get('gestartet_am'));
                        $bis = \Illuminate\Support\Carbon::parse($state);

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
                        : self::alsStunden($record->minuten))
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
                    ->label('Zeit starten')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    // Nur anbieten, wenn gerade nichts läuft. Zwei parallel
                    // laufende Uhren derselben Person wären nicht auflösbar —
                    // niemand arbeitet an zwei Tickets gleichzeitig.
                    ->visible(fn () => auth()->user()->laufendeZeit() === null)
                    ->action(function () {
                        TimeEntry::create([
                            'ticket_id' => $this->getOwnerRecord()->getKey(),
                            'user_id' => auth()->id(),
                            'gestartet_am' => now(),
                        ]);

                        Notification::make()
                            ->title('Zeiterfassung läuft')
                            ->success()
                            ->send();
                    }),

                Action::make('stoppen')
                    ->label('Zeit stoppen')
                    ->icon('heroicon-o-stop')
                    ->color('warning')
                    ->visible(fn () => auth()->user()->laufendeZeit() !== null)
                    ->action(function () {
                        $laufend = auth()->user()->laufendeZeit();

                        if ($laufend === null) {
                            return;
                        }

                        $laufend->stoppen();

                        Notification::make()
                            ->title('Zeit erfasst: '.self::alsStunden($laufend->minuten))
                            // Hinweis, falls die laufende Uhr an einem anderen
                            // Ticket hing — sonst wundert man sich, warum hier
                            // nichts erscheint.
                            ->body($laufend->ticket_id === $this->getOwnerRecord()->getKey()
                                ? null
                                : 'Die Buchung gehörte zu '.$laufend->ticket->kennung().'.')
                            ->success()
                            ->send();
                    }),

                CreateAction::make()
                    ->label('Zeit nachtragen')
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
            ->emptyStateDescription('Starte die Uhr oder trage eine Zeit von Hand nach.');
    }

    /** 135 Minuten werden als "2:15 h" lesbarer als als "135". */
    private static function alsStunden(int $minuten): string
    {
        return intdiv($minuten, 60).':'.str_pad((string) ($minuten % 60), 2, '0', STR_PAD_LEFT).' h';
    }
}
