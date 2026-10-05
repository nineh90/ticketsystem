<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Customer;
use App\Support\Anfrage;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;

/**
 * Die Arbeitsfläche für ein einzelnes Ticket: Beschreibung oben, darunter
 * Kommentare, Zeiten und Verlauf als Reiter.
 */
class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Wer das Ticket öffnet, hat die Meldungen dazu gesehen.
     *
     * Der Knopf in der Glocke führt genau hierher — wer ihn benutzt, hat sie
     * damit gelesen, und wer den Weg über die Ticketliste nimmt, ebenso. Ohne
     * das trüge man die Zahl an der Glocke auch dann weiter vor sich her,
     * wenn man die Antwort längst kennt, und nach der dritten Woche sagt eine
     * Zahl, die immer da ist, gar nichts mehr.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        Benachrichtigung::gesehen(auth()->user(), Herkunft::ticket($this->record));
    }

    public function getTitle(): string
    {
        return $this->record->kennung().' — '.$this->record->titel;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->interessentAnlegen(),
            EditAction::make()->label('Bearbeiten'),
        ];
    }

    /**
     * Aus der Anfrage einen Kunden machen, ohne Name und Adresse abzutippen.
     *
     * Ein Formular und kein blinder Klick, weil zwei Dinge nicht aus dem
     * Ticket hervorgehen: wie der Kunde heißen soll (eine Person oder ihre
     * Firma) und sein Kürzel, das danach in jeder Ticketnummer steht. Beides
     * ist vorbelegt; im Normalfall bleibt es beim Bestätigen.
     *
     * Ist die Absenderadresse schon bei einem Kunden bekannt, steht die
     * Zuordnung dorthin als Erstes zur Wahl — sonst hätte derselbe Mensch
     * nach der zweiten Anfrage zwei Kundenakten.
     */
    private function interessentAnlegen(): Action
    {
        return Action::make('interessent')
            ->label('Als Interessent anlegen')
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            // Kunden legt nur an, wer das auch in der Kundenliste dürfte.
            ->visible(fn () => Anfrage::offenFuer($this->record)
                && auth()->user()->can('create', Customer::class))
            ->modalHeading('Aus der Anfrage einen Kunden machen')
            ->modalDescription(fn () => 'Das Ticket bleibt als '.$this->record->kennung()
                .' bestehen — der Link aus der Website stimmt weiter. Der Kunde verweist darauf.')
            ->modalSubmitActionLabel('Übernehmen')
            ->fillForm(function (): array {
                $name = (string) ($this->record->absender_name ?: $this->record->absender_email);

                return [
                    'weg' => Anfrage::bekannterKunde($this->record) !== null ? 'bestehend' : 'neu',
                    'kontakt_name' => $this->record->absender_name,
                    'email' => $this->record->absender_email,
                    'kundenname' => $name,
                    'kuerzel' => Anfrage::kuerzelVorschlag($name),
                ];
            })
            ->schema(function (): array {
                $bekannt = Anfrage::bekannterKunde($this->record);

                return [
                    Radio::make('weg')
                        ->label('Diese Adresse ist schon bekannt')
                        ->options([
                            'bestehend' => 'Zu '.$bekannt?->name.' zuordnen',
                            'neu' => 'Trotzdem einen neuen Kunden anlegen',
                        ])
                        ->live()
                        ->required()
                        ->visible($bekannt !== null),

                    TextInput::make('kontakt_name')
                        ->label('Name')
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('E-Mail')
                        ->email()
                        ->maxLength(255),

                    TextInput::make('kundenname')
                        ->label('Kunde')
                        ->helperText('Die Person selbst oder ihre Firma.')
                        ->required()
                        ->maxLength(255)
                        ->visible(fn (Get $get) => $get('weg') !== 'bestehend'),

                    // Dieselben Regeln wie im Kundenformular.
                    TextInput::make('kuerzel')
                        ->label('Kürzel')
                        ->helperText('Steht in jeder Ticketnummer dieses Kunden: LDX-42.')
                        ->required()
                        ->minLength(2)
                        ->maxLength(5)
                        ->alphaNum()
                        // Von Hand statt über unique(): das würde das
                        // Ticket dieser Seite als "eigenen Datensatz"
                        // ausnehmen wollen, und es vergliche "eri" nicht mit
                        // "ERI" — gespeichert wird aber immer groß.
                        ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                            if (Customer::whereRaw('upper(kuerzel) = ?', [mb_strtoupper((string) $value)])->exists()) {
                                $fail('Dieses Kürzel ist schon vergeben.');
                            }
                        })
                        ->visible(fn (Get $get) => $get('weg') !== 'bestehend'),
                ];
            })
            ->action(function (array $data): void {
                // Noch einmal nachsehen statt dem Formular zu glauben: der
                // bekannte Kunde wird nicht als Wert mitgeschickt, sondern
                // hier aus dem Ticket bestimmt.
                $bekannt = Anfrage::bekannterKunde($this->record);

                $kunde = ($data['weg'] ?? 'neu') === 'bestehend' && $bekannt !== null
                    ? Anfrage::zuordnen($this->record, $bekannt, $data['kontakt_name'] ?? null, $data['email'] ?? null)
                    : Anfrage::alsInteressent($this->record, $data);

                Notification::make()
                    ->title($kunde->wasRecentlyCreated ? 'Interessent angelegt' : 'Anfrage zugeordnet')
                    ->body($kunde->name)
                    ->success()
                    ->actions([
                        Action::make('oeffnen')
                            ->label('Zur Kundenakte')
                            ->url(CustomerResource::getUrl('view', ['record' => $kunde])),
                    ])
                    ->send();
            });
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Ganz oben, wenn draußen jemand wartet. Das steht zwar auch als
            // Herkunft weiter unten in den Eckdaten, aber dort ist es eine
            // Angabe unter neun anderen — hier ist es das Erste, was man
            // liest, und es ändert, wie dringend das Ticket ist.
            Section::make('Vom Kunden gemeldet')
                ->icon('heroicon-o-inbox-arrow-down')
                ->description(fn () => $this->record->customer->name
                    .' wartet auf eine Antwort. Kommentare erreichen den Kunden nur, wenn "Interne Notiz" ausgeschaltet ist.')
                ->schema([])
                ->visible(fn () => $this->record->istVomKunden()),

            Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('status.name')
                        ->label('Status')
                        ->badge()
                        ->color(fn () => Color::hex($this->record->status->farbe)),

                    TextEntry::make('art')
                        ->label('Art')
                        ->badge(),

                    TextEntry::make('prioritaet')
                        ->label('Priorität')
                        ->badge(),

                    TextEntry::make('zustaendig.name')
                        ->label('Zuständig')
                        ->placeholder('Niemand'),

                    TextEntry::make('faellig_am')
                        ->label('Fällig')
                        ->date('d.m.Y')
                        ->placeholder('—')
                        ->color(fn () => $this->record->faellig_am
                            && $this->record->faellig_am->isPast()
                            && ! $this->record->erledigt_at
                                ? 'danger'
                                : null),

                    TextEntry::make('project.name')
                        ->label('Projekt')
                        ->url(fn () => route('filament.admin.resources.projects.edit', $this->record->project)),

                    TextEntry::make('customer.name')
                        ->label('Kunde'),

                    TextEntry::make('erfasste_zeit')
                        ->label('Erfasste Zeit')
                        ->state(function () {
                            $minuten = $this->record->erfassteMinuten();

                            return intdiv($minuten, 60).':'
                                .str_pad((string) ($minuten % 60), 2, '0', STR_PAD_LEFT).' h';
                        }),

                    TextEntry::make('quelle')
                        ->label('Herkunft')
                        ->badge(),

                    // Wer geschrieben hat — nur bei eingelieferten Tickets
                    // gefüllt, sonst gar nicht erst als leeres Feld da.
                    TextEntry::make('absender_name')
                        ->label('Absender')
                        ->visible(fn () => filled($this->record->absender_name)),

                    TextEntry::make('absender_email')
                        ->label('Absender-Adresse')
                        ->copyable()
                        ->visible(fn () => filled($this->record->absender_email)),

                    TextEntry::make('interessent.name')
                        ->label('Daraus wurde')
                        ->url(fn () => CustomerResource::getUrl('view', ['record' => $this->record->interessent]))
                        ->visible(fn () => $this->record->interessent !== null),
                ]),

            // Bilder direkt unter den Eckdaten. Wer ein Ticket öffnet, soll
            // den Screenshot sehen, ohne erst einen Reiter zu suchen — das
            // ist der ganze Zweck der Anhänge bei Fehlerberichten.
            Section::make('Bilder')
                ->schema([
                    ViewEntry::make('bilder')
                        ->hiddenLabel()
                        ->view('filament.ticket-bilder'),
                ])
                ->visible(fn () => $this->record->bilder()->exists()),

            Section::make('Beschreibung')
                ->schema([
                    TextEntry::make('beschreibung')
                        ->hiddenLabel()
                        ->placeholder('Keine Beschreibung hinterlegt.')
                        ->prose(),
                ])
                ->collapsible(),
        ]);
    }
}
