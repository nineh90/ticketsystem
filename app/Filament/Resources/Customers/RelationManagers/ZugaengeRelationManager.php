<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\Rolle;
use App\Models\User;
use App\Support\Einladen;
use App\Support\Startpasswort;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Die Kundenzugänge zu diesem Kunden.
 *
 * Bewusst hier und nicht in der Nutzerverwaltung: ein Kundenzugang ist kein
 * Nutzer, den man zufällig einem Kunden zuordnet, sondern ein Zugang, den
 * dieser Kunde bekommt. In der allgemeinen Nutzerliste müsste man Rolle,
 * Kunde und Freigabe einzeln richtig setzen und könnte dabei jeden der drei
 * vergessen — hier ergibt sich alles aus dem Kunden, bei dem man gerade steht.
 *
 * Seit dem 01.09.2026 läuft das Anlegen über eine Einladung: der Kunde
 * bekommt eine Mail und vergibt sich sein Passwort selbst. Davor vergab ein
 * Administrator ein Startpasswort und gab es weiter — das setzte voraus, dass
 * er es tatsächlich tut, und war auch uns bekannt. Von fünf Kundenzugängen
 * hatten sich an dem Tag zwei noch nie angemeldet.
 *
 * Der alte Weg steht weiter offen (Feld "Startpasswort" ausfüllen), für den
 * Fall, dass die Daten ausnahmsweise am Telefon durchgegeben werden.
 */
class ZugaengeRelationManager extends RelationManager
{
    protected static string $relationship = 'zugaenge';

    protected static ?string $title = 'Zugänge';

    protected static ?string $modelLabel = 'Zugang';

    protected static ?string $pluralModelLabel = 'Zugänge';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-key';

    /**
     * Ob der eben angelegte Zugang eine Einladung bekommen soll.
     *
     * Gesetzt beim Umformen der Formulardaten, gelesen danach — siehe die
     * Begründung an beiden Stellen. Eine Eigenschaft und kein zweiter Blick
     * auf den Datensatz, weil man ihm nicht mehr ansieht, woher sein
     * Passwort kam.
     */
    private bool $einladen = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    /** Nur Administratoren vergeben Zugänge. */
    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->istAdmin();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255)
                ->helperText('Steht unter jeder Antwort dieser Person. Bei mehreren Zugängen sieht man daran, mit wem man schreibt.'),

            TextInput::make('email')
                ->label('E-Mail')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(User::class, 'email', ignoreRecord: true)
                ->helperText('Damit meldet sich der Kunde an.'),

            TextInput::make('password')
                ->label('Startpasswort')
                ->password()
                ->revealable()
                ->minLength(10)
                // Seit dem 01.09.2026 beim Anlegen NICHT mehr erforderlich
                // und auch nicht mehr vorbelegt: leer lassen ist der
                // Regelfall, dann bekommt der Kunde eine Einladung und
                // vergibt sich selbst eines (siehe Einladen). Das Feld bleibt
                // für den Fall, dass jemand keine brauchbare Adresse hat und
                // die Zugangsdaten wirklich am Telefon durchgegeben werden.
                ->required(false)
                // Beim Bearbeiten heißt leer "unverändert" — ohne diesen
                // Filter überschriebe ein leeres Feld das Passwort und
                // sperrte den Kunden aus.
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                // Kein Vorschlag mehr: ein vorbelegtes Feld sieht aus, als
                // müsste es ausgefüllt werden, und genau das soll es nicht.
                ->helperText('Leer lassen — der Kunde bekommt eine Einladung per Mail und vergibt sich selbst eines, das wir nie kennen. Nur ausfüllen, wenn die Daten ausnahmsweise am Telefon durchgegeben werden; dann notieren und weitergeben, bevor Sie speichern.'),

            // Verknüpfung zur Person im Reiter "Kontakte". Optional: ein
            // Zugang funktioniert auch ohne. Wo sie gesetzt ist, steht die
            // Person einmal im System statt zweimal — mit zwei Telefonnummern,
            // von denen eine veraltet.
            Select::make('kontakt_id')
                ->label('Ist welcher Kontakt?')
                ->options(fn () => $this->getOwnerRecord()->kontakte()->aktiv()->inReihenfolge()->pluck('name', 'id'))
                ->searchable()
                ->placeholder('Nicht zugeordnet')
                ->helperText('Nur Kontakte dieses Kunden. Anlegen im Reiter "Kontakte".'),

            // Beim ersten Zugang eines Kunden voreingestellt an: dort gibt es
            // niemanden, dem man etwas wegnähme, und irgendwer muss die
            // Anschrift pflegen können. Ab dem zweiten aus — der Vorstand
            // bestimmt über die Rechnungsanschrift, nicht die Person, die
            // die Website betreut.
            Toggle::make('stammdaten_pflegen')
                ->label('Darf Firmendaten pflegen')
                ->default(fn () => $this->getOwnerRecord()->zugaenge()->doesntExist())
                ->helperText('Anschrift, Rechnungsadresse, USt-IdNr. Sehen können alle Zugänge diese Angaben, ändern nur die mit diesem Haken. Jede Änderung meldet uns das System.'),

            Toggle::make('panel_zugang')
                ->label('Zugang freigegeben')
                ->default(true)
                ->helperText('Aus: der Zugang bleibt bestehen, kommt aber nicht mehr hinein. Der schnelle Weg, jemanden vorübergehend auszusperren.'),

            Toggle::make('aktiv')
                ->label('Aktiv')
                ->default(true)
                ->helperText('Ausgeschiedene Ansprechpartner deaktivieren statt löschen — ihre gemeldeten Anliegen und Antworten bleiben zuordenbar.'),
        ]);
    }

    /**
     * Die Adresse steht über der Liste, nicht nur in einer Benachrichtigung
     * nach dem Anlegen.
     *
     * Beim ersten Kundenzugang wurden die Daten an der internen Anmeldung
     * eingegeben und dort abgewiesen — der Hinweis, dass der Zugang eine
     * Adresse weiter gilt, stand zu dem Zeitpunkt in einer Meldung, die längst
     * weggeklickt war. Hier steht er dauerhaft, an der Stelle, an der man die
     * Zugangsdaten heraussucht.
     */
    public function getTableDescription(): ?string
    {
        return 'Kundenzugänge melden sich unter '.route('filament.kunde.auth.login')
            .' an — nicht am internen Anmeldeformular.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->weight('medium')
                    ->description(fn (User $record) => $record->email),

                IconColumn::make('panel_zugang')
                    ->label('Freigegeben')
                    ->boolean(),

                IconColumn::make('aktiv')
                    ->label('Aktiv')
                    ->boolean(),

                // Zeigt, dass gerade ein von uns vergebenes Passwort in
                // Gebrauch ist. Verschwindet von selbst, sobald der Kunde
                // sein eigenes gesetzt hat — insofern auch die Antwort auf
                // "ist das Startpasswort angekommen?".
                IconColumn::make('passwort_wechseln')
                    ->label('Startpasswort')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (User $record) => $record->passwort_wechseln
                        ? 'Nutzt noch das zugeteilte Passwort und wird beim Anmelden zum Wechsel geführt'
                        : 'Hat ein eigenes Passwort'),

                TextColumn::make('letzte_anmeldung_at')
                    ->label('Zuletzt angemeldet')
                    ->since()
                    // Die wichtigste Spalte, nachdem man ein Startpasswort
                    // weitergegeben hat: "noch nie" heißt, dass die
                    // Weitergabe nicht angekommen ist — und nicht, dass der
                    // Kunde kein Interesse hat.
                    ->placeholder('noch nie')
                    ->color(fn (User $record) => $record->letzte_anmeldung_at === null ? 'warning' : null)
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->headerActions([
                CreateAction::make()
                    ->label('Zugang anlegen')
                    ->icon('heroicon-o-key')
                    ->modalHeading('Kundenzugang anlegen')
                    // Rolle und Kundenzugehörigkeit werden gesetzt, nicht
                    // gewählt. Die Rolle entscheidet darüber, in welches
                    // Panel dieser Zugang darf (User::canAccessPanel) — sie
                    // hier zur Auswahl zu stellen hieße, aus Versehen einen
                    // Mitarbeiterzugang mit Kundennamen anlegen zu können.
                    ->mutateDataUsing(function (array $data): array {
                        $data['rolle'] = Rolle::Kunde->value;
                        $data['customer_id'] = $this->getOwnerRecord()->getKey();

                        // Ob eingeladen wird, entscheidet sich hier und nicht
                        // hinterher am Datensatz: dort ist nicht mehr zu
                        // erkennen, ob das Passwort von Hand kam oder von
                        // uns. passwort_wechseln taugt dafür nicht — es steht
                        // in beiden Fällen auf true, weil in beiden Fällen
                        // ein anderer als der Kontoinhaber es gesetzt hat
                        // (User::booted).
                        $this->einladen = blank($data['password'] ?? null);

                        // Ohne eingetipptes Startpasswort bekommt das Konto
                        // eines, das niemand kennt und auch niemand kennen
                        // soll — der Kunde setzt sich über die Einladung sein
                        // eigenes. Leer bleiben darf die Spalte nicht, und
                        // ein erratbarer Wert wäre ein offenes Konto, solange
                        // die Einladung noch nicht eingelöst ist.
                        if ($this->einladen) {
                            $data['password'] = Str::random(48);
                        }

                        return $data;
                    })
                    ->after(function (User $record): void {
                        // Wurde ein Passwort von Hand vergeben, ist der
                        // Kunde auf dem alten Weg unterwegs: dann keine
                        // Einladung, sonst bekäme er zwei Wege für dieselbe
                        // Sache und wüsste nicht, welcher gilt.
                        if (! $this->einladen || blank($record->email)) {
                            return;
                        }

                        if (Einladen::schicken($record)) {
                            Notification::make()
                                ->title('Zugang angelegt und Einladung verschickt')
                                ->body('An '.$record->email.' ist eine Mail unterwegs. '
                                    .str($record->name)->before(' ').' vergibt sich damit ein eigenes Passwort — '
                                    .'Sie müssen nichts weitergeben.')
                                ->success()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Zugang angelegt — Einladung nicht zugestellt')
                            ->body('Der Zugang steht, die Mail an '.$record->email.' ging nicht hinaus. '
                                .'Über "Einladung schicken" in der Liste noch einmal versuchen, '
                                .'oder unter "Passwort neu setzen" eines von Hand vergeben.')
                            ->warning()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),

                /*
                 * Die Einladung noch einmal schicken.
                 *
                 * Der Anlass ist der Bestand: am 01.09.2026 hatten sich zwei
                 * von fünf Kundenzugängen noch nie angemeldet — angelegt im
                 * August, Startpasswort vergeben, und dann versandet. Für die
                 * gibt es ohne diesen Knopf keinen Weg zurück außer einem
                 * Anruf.
                 *
                 * Auch der richtige Griff, wenn der Link abgelaufen ist: er
                 * gilt eine Stunde, und eine Einladung liegt gern über Nacht
                 * im Postfach.
                 */
                Action::make('einladen')
                    ->label('Einladung schicken')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn (User $record) => filled($record->email))
                    ->requiresConfirmation()
                    ->modalHeading('Einladung schicken')
                    ->modalDescription(fn (User $record) => 'An '.$record->email
                        .' geht eine Mail mit einem Link, über den '
                        .str($record->name)->before(' ').' sich ein eigenes Passwort vergibt. '
                        .'Ein bereits gesetztes Passwort bleibt gültig, bis der Link benutzt wird.')
                    ->modalSubmitActionLabel('Schicken')
                    ->action(function (User $record): void {
                        if (Einladen::schicken($record)) {
                            Notification::make()
                                ->title('Einladung verschickt')
                                ->body('An '.$record->email.'. Der Link gilt eine Stunde.')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Einladung ging nicht hinaus')
                            ->body('Der Versand ist gescheitert — Näheres steht im Protokoll. '
                                .'Notfalls unter "Passwort neu setzen" eines von Hand vergeben.')
                            ->danger()
                            ->send();
                    }),

                // Ein eigener Knopf statt "Bearbeiten": ein vergessenes
                // Passwort ist der häufigste Handgriff hier, und dafür soll
                // niemand ein Formular mit fünf Feldern öffnen, in dem er
                // versehentlich die Freigabe umlegt.
                Action::make('passwort')
                    ->label('Passwort neu setzen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->schema([
                        TextInput::make('password')
                            ->label('Neues Passwort')
                            ->password()
                            ->revealable()
                            ->minLength(10)
                            ->required()
                            ->default(fn () => Startpasswort::erzeugen())
                            ->helperText('Notieren und weitergeben, bevor Sie speichern.'),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update(['password' => Hash::make($data['password'])]);

                        Notification::make()
                            ->title('Passwort gesetzt')
                            ->body('Geben Sie es '.$record->name.' weiter. Beim nächsten Anmelden wird '
                                .str($record->name)->before(' ').' aufgefordert, ein eigenes zu vergeben.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateHeading('Noch kein Zugang')
            ->emptyStateDescription('Mit einem Zugang sieht dieser Kunde unter /kunde seine Projekte, den Stand seiner Anliegen und kann selbst welche melden.');
    }
}
