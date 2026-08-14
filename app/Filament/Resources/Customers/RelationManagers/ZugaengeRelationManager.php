<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\Rolle;
use App\Models\User;
use App\Support\Startpasswort;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * Die Kundenzugänge zu diesem Kunden.
 *
 * Bewusst hier und nicht in der Nutzerverwaltung: ein Kundenzugang ist kein
 * Nutzer, den man zufällig einem Kunden zuordnet, sondern ein Zugang, den
 * dieser Kunde bekommt. In der allgemeinen Nutzerliste müsste man Rolle,
 * Kunde und Freigabe einzeln richtig setzen und könnte dabei jeden der drei
 * vergessen — hier ergibt sich alles aus dem Kunden, bei dem man gerade steht.
 *
 * Solange kein Mailversand eingerichtet ist, vergibt der Administrator ein
 * Startpasswort und gibt es weiter; der Kunde ändert es unter "Profil".
 */
class ZugaengeRelationManager extends RelationManager
{
    protected static string $relationship = 'zugaenge';

    protected static ?string $title = 'Zugänge';

    protected static ?string $modelLabel = 'Zugang';

    protected static ?string $pluralModelLabel = 'Zugänge';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-key';

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
                ->required(fn (string $operation) => $operation === 'create')
                // Beim Bearbeiten heißt leer "unverändert" — ohne diesen
                // Filter überschriebe ein leeres Feld das Passwort und
                // sperrte den Kunden aus.
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                // Ein brauchbarer Vorschlag steht schon drin. Sonst wird es
                // erfahrungsgemäß "kunde2026" — und das für jeden Kunden.
                ->default(fn (string $operation) => $operation === 'create' ? Startpasswort::erzeugen() : null)
                ->helperText('Notieren und weitergeben, bevor Sie speichern — danach ist es nicht mehr lesbar. Der Kunde ändert es selbst unter "Profil".'),

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

                        return $data;
                    })
                    ->after(fn () => Notification::make()
                        ->title('Zugang angelegt')
                        ->body('Geben Sie dem Kunden die E-Mail-Adresse, das Startpasswort und die Adresse '.route('filament.kunde.auth.login').' weiter.')
                        ->success()
                        ->persistent()
                        ->send()),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),

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
                            ->body('Geben Sie es '.$record->name.' weiter.')
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
