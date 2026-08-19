<?php

namespace App\Filament\Kunde\Pages;

use App\Enums\MailEreignis;
use App\Models\Customer;
use App\Models\Kontakt;
use App\Support\Adressbestaetigung;
use App\Support\Benachrichtigung;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use SensitiveParameter;

/**
 * "Mein Konto" — die Profilseite des Kundenbereichs.
 *
 * Sie kann mehr als Filaments Vorlage, und zwar aus einem handfesten Grund:
 * die Daten eines Kunden sind bei ihm richtig und bei uns abgeschrieben. Wer
 * umzieht, weiß das vor uns; wer die Buchhaltung wechselt, auch. Bisher
 * mussten solche Änderungen durch einen Anruf und von dort in ein Formular,
 * das jemand von uns öffnet — mit einer Verzögerung von Tagen und dem
 * Risiko, dass es niemand tut.
 *
 * Bearbeitbar ist deshalb, was ihm gehört: sein Zugang, seine Telefonnummer,
 * die Anschrift seiner Firma. Nicht bearbeitbar ist, was unsere Sicht auf
 * ihn ist — Kürzel, Betreuungsstand, Vertrag, Farbe. Das steht hier nicht
 * einmal zum Ansehen.
 *
 * Änderungen an den Firmendaten melden wir uns selbst (siehe afterSave).
 * Eine stille Änderung der Rechnungsanschrift fällt sonst erst auf, wenn die
 * nächste Rechnung zurückkommt.
 *
 * **Die Seite steht im Anzeigemodus, bis jemand auf "Bearbeiten" drückt.**
 * Vorher lag hier sofort ein Formular mit dreizehn Eingabefeldern, und das
 * ist die falsche Antwort auf die Frage, mit der ein Kunde herkommt: er will
 * meistens nur nachsehen, ob die Anschrift stimmt. Ein Formular sagt ihm
 * stattdessen "hier ist etwas auszufüllen" — und wer nur nachsehen wollte,
 * verlässt es im Zweifel mit einem halb geänderten Feld. Ändern kann er
 * weiterhin alles, was ihm gehört; es ist nur ein Knopf davor.
 */
class Profil extends EditProfile
{
    /**
     * Die Werte der beiden Nebenmodelle, zwischen Formular und Speichern
     * geparkt. Sie gehören nicht an den Nutzer und würden dort beim
     * Speichern als unbekannte Spalten auffliegen.
     *
     * @var array<string, mixed>
     */
    protected array $kundendaten = [];

    protected array $kontaktdaten = [];

    /**
     * Steht die Seite gerade im Bearbeitungsmodus?
     *
     * Öffentlich, weil Livewire nur öffentliche Eigenschaften über die
     * Anfrage hinweg behält — als geschützte wäre der Modus nach dem ersten
     * Klick wieder weg.
     */
    public bool $bearbeiten = false;

    /**
     * Kam dieser Aufruf mit einem zugeteilten Passwort herein?
     *
     * Wird beim Aufbau der Seite festgehalten und nicht später erfragt: nach
     * dem Speichern ist der Schalter am Nutzer gelöscht, und dann ließe sich
     * nicht mehr unterscheiden, ob jemand gerade den Zwangswechsel hinter
     * sich gebracht hat oder ohnehin nur seine Anschrift gepflegt hat. Davon
     * hängt ab, wohin es danach geht (siehe getRedirectUrl).
     */
    public bool $kamMitZugeteiltemPasswort = false;

    public function mount(): void
    {
        parent::mount();

        $this->kamMitZugeteiltemPasswort = $this->mussWechseln();
    }

    public function getTitle(): string
    {
        return 'Mein Konto';
    }

    public function getHeading(): string
    {
        return 'Mein Konto';
    }

    public function getSubheading(): ?string
    {
        return $this->mussWechseln()
            ? 'Bitte vergeben Sie zuerst ein eigenes Passwort.'
            : 'Ihr Zugang und die Daten Ihres Unternehmens.';
    }

    /**
     * Steht dieser Zugang noch auf einem zugeteilten Passwort?
     *
     * Solange das gilt, ist diese Seite eine Hürde vor dem eigentlichen Ziel
     * — und zeigt deshalb nur das, was die Hürde wegnimmt.
     */
    protected function mussWechseln(): bool
    {
        return (bool) $this->getUser()->passwort_wechseln;
    }

    /**
     * Darf dieser Zugang die Firmendaten ändern?
     *
     * Ein Kunde hat oft mehrere Zugänge, und nicht jeder davon ist derjenige,
     * der über die Rechnungsanschrift bestimmt.
     */
    protected function darfStammdaten(): bool
    {
        return (bool) $this->getUser()->stammdaten_pflegen;
    }

    public function form(Schema $schema): Schema
    {
        // Beim erzwungenen Wechsel nur das Passwort. Ein Formular mit zehn
        // weiteren Feldern liest sich an dieser Stelle wie "füllen Sie erst
        // alles aus" — dabei ist nur eines gemeint, und der Rest steht dem
        // Zugang danach ohnehin offen.
        if ($this->mussWechseln()) {
            return $schema->components([
                Section::make('Neues Passwort')
                    ->description('Ihr jetziges Passwort haben wir Ihnen zugeteilt und kennen es deshalb. Vergeben Sie eines, das nur Sie kennen — danach geht es weiter zur Übersicht.')
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
        }

        if (! $this->bearbeiten) {
            return $this->anzeige($schema);
        }

        return $schema->components([
            Section::make('Ihr Zugang')
                ->description('Name und E-Mail-Adresse, mit der Sie sich anmelden.')
                ->schema([
                    $this->getNameFormComponent(),
                    $this->getEmailFormComponent(),

                    TextInput::make('kontakt_telefon')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(255)
                        ->helperText('Für Rückfragen, bei denen ein Anruf schneller ist als drei Nachrichten.'),
                ]),

            Section::make('Benachrichtigungen')
                ->description('Wir schreiben Ihnen nur, wenn Sie das möchten — und nur worüber Sie es möchten.')
                ->schema([
                    Toggle::make('mail_benachrichtigungen')
                        ->label('Ja, informieren Sie mich per E-Mail')
                        ->live()
                        ->helperText('Jederzeit hier wieder abschaltbar.'),

                    TextInput::make('benachrichtigungs_email')
                        ->label('An diese Adresse')
                        ->email()
                        ->maxLength(255)
                        ->required(fn (Get $get) => (bool) $get('mail_benachrichtigungen'))
                        ->visible(fn (Get $get) => (bool) $get('mail_benachrichtigungen'))
                        // Der Hinweis ist der Kern des Ganzen: eine Adresse,
                        // auf die niemand zugreift, sieht bis zum
                        // Bestätigungsklick genauso aus wie eine richtige —
                        // und danach wartet jemand wochenlang auf Post, die
                        // nie kommt.
                        ->helperText('Bitte eine Adresse, die Sie tatsächlich abrufen können — wir schicken einen Bestätigungslink dorthin, und ohne den Klick darin bekommen Sie keine Post. Sie darf eine andere sein als die, mit der Sie sich anmelden.'),

                    CheckboxList::make('mail_ereignisse')
                        ->label('Worüber')
                        ->options(collect(MailEreignis::fuerKunden())
                            ->mapWithKeys(fn (MailEreignis $e) => [$e->value => $e->getKundenLabel()])
                            ->all())
                        ->descriptions(collect(MailEreignis::fuerKunden())
                            ->mapWithKeys(fn (MailEreignis $e) => [$e->value => $e->getKundenDescription()])
                            ->all())
                        ->default(array_map(fn (MailEreignis $e) => $e->value, MailEreignis::fuerKunden()))
                        ->visible(fn (Get $get) => (bool) $get('mail_benachrichtigungen')),
                ]),

            Section::make('Passwort ändern')
                ->description('Leer lassen, wenn Ihr Passwort so bleiben soll.')
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),

            Section::make('Ihr Unternehmen')
                ->description($this->darfStammdaten()
                    ? 'Diese Angaben verwenden wir für Rechnungen und Schriftverkehr. Ändern Sie sie gern selbst — wir bekommen darüber Bescheid.'
                    : 'Diese Angaben verwenden wir für Rechnungen und Schriftverkehr. Ändern kann sie der Zugang, der bei Ihnen dafür zuständig ist — stimmt etwas nicht, sagen Sie uns kurz Bescheid.')
                // Auch ohne Änderungsrecht sichtbar: wer nachsehen will, ob
                // die Anschrift stimmt, soll das können, ohne anzurufen.
                ->disabled(! $this->darfStammdaten())
                ->schema([
                    // Der Firmenname steht bewusst nur zum Ansehen da: an ihm
                    // hängen das Kürzel in jeder Ticketnummer und die
                    // Zuordnung sämtlicher Projekte. Eine Umfirmierung ist ein
                    // Anruf wert, kein Formularfeld.
                    TextInput::make('kunde_name')
                        ->label('Firma')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Sie heißen jetzt anders? Sagen Sie uns Bescheid, wir ändern es.'),

                    TextInput::make('kunde_strasse')
                        ->label('Straße und Hausnummer')
                        ->maxLength(255),

                    TextInput::make('kunde_plz')
                        ->label('PLZ')
                        ->maxLength(10),

                    TextInput::make('kunde_ort')
                        ->label('Ort')
                        ->maxLength(255),

                    TextInput::make('kunde_land')
                        ->label('Land')
                        ->maxLength(2)
                        ->placeholder('DE'),

                    TextInput::make('kunde_rechnung_email')
                        ->label('Rechnungen an')
                        ->email()
                        ->maxLength(255)
                        ->helperText('Wenn Rechnungen woandershin sollen als an Sie — etwa in die Buchhaltung.'),

                    TextInput::make('kunde_ust_id')
                        ->label('USt-IdNr.')
                        ->maxLength(20)
                        ->placeholder('DE123456789'),

                    TextInput::make('kunde_website')
                        ->label('Website')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://…'),
                ]),
        ]);
    }

    /**
     * Dieselben Angaben, nur zum Lesen.
     *
     * Bewusst als Schema und nicht als zweite Seite: Filaments Schemas
     * tragen seit Version 4 Eingabefelder und Anzeigefelder nebeneinander,
     * und damit bleibt alles an einer Stelle — Beschriftungen, Reihenfolge
     * und Abschnitte sind in beiden Modi dieselben. Zwei Dateien, die
     * dasselbe Formular einmal zum Lesen und einmal zum Schreiben
     * beschreiben, laufen beim ersten neuen Feld auseinander.
     *
     * Die Werte kommen direkt aus den Modellen und nicht aus dem
     * Formularzustand: im Anzeigemodus gibt es keinen, der gefüllt sein
     * müsste, und ein leeres Feld sähe aus wie eine fehlende Angabe.
     */
    private function anzeige(Schema $schema): Schema
    {
        $nutzer = $this->getUser();
        $kunde = $this->kunde();

        return $schema->components([
            Section::make('Ihr Zugang')
                ->description('Name und E-Mail-Adresse, mit der Sie sich anmelden.')
                ->columns(2)
                ->schema([
                    TextEntry::make('anzeige_name')
                        ->label('Name')
                        ->state($nutzer->name),

                    TextEntry::make('anzeige_email')
                        ->label('E-Mail')
                        ->state($nutzer->email)
                        ->copyable(),

                    TextEntry::make('anzeige_telefon')
                        ->label('Telefon')
                        ->state($nutzer->kontakt?->telefon)
                        ->placeholder('nicht hinterlegt'),

                    // Punkte statt eines leeren Feldes: dass ein Passwort
                    // gesetzt ist, weiß der Kunde — die Zeile steht hier,
                    // damit er sieht, wo er es ändern kann.
                    TextEntry::make('anzeige_passwort')
                        ->label('Passwort')
                        ->state('••••••••')
                        ->helperText('Ändern über "Bearbeiten".'),
                ]),

            Section::make('Benachrichtigungen')
                ->description('Ob und worüber wir Ihnen schreiben.')
                ->columns(2)
                ->schema([
                    TextEntry::make('anzeige_benachrichtigungen')
                        ->label('Status')
                        ->state(fn () => $this->benachrichtigungsStand())
                        ->badge()
                        ->color(fn () => match (true) {
                            $nutzer->bekommtMailMeldungen() => 'success',
                            $nutzer->wartetAufAdressbestaetigung() => 'warning',
                            default => 'gray',
                        }),

                    TextEntry::make('anzeige_benachrichtigungs_email')
                        ->label('An diese Adresse')
                        ->state($nutzer->benachrichtigungs_email)
                        ->placeholder('keine hinterlegt'),

                    TextEntry::make('anzeige_benachrichtigungs_themen')
                        ->label('Worüber')
                        ->state(fn () => $this->gewaehlteThemen())
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->visible(fn () => (bool) $nutzer->mail_benachrichtigungen),
                ]),

            Section::make('Ihr Unternehmen')
                ->description($this->darfStammdaten()
                    ? 'Diese Angaben verwenden wir für Rechnungen und Schriftverkehr.'
                    : 'Diese Angaben verwenden wir für Rechnungen und Schriftverkehr. Ändern kann sie der Zugang, der bei Ihnen dafür zuständig ist — stimmt etwas nicht, sagen Sie uns kurz Bescheid.')
                ->columns(2)
                ->visible($kunde !== null)
                ->schema([
                    TextEntry::make('anzeige_firma')
                        ->label('Firma')
                        ->state($kunde?->name),

                    // Im Anzeigemodus eine Zeile statt vier Felder: so liest
                    // man eine Anschrift, und so steht sie auch auf der
                    // Rechnung. Aufgeteilt wird sie erst beim Bearbeiten.
                    TextEntry::make('anzeige_anschrift')
                        ->label('Anschrift')
                        ->state(fn () => $kunde?->anschrift())
                        ->placeholder('nicht hinterlegt'),

                    TextEntry::make('anzeige_rechnung_email')
                        ->label('Rechnungen an')
                        ->state($kunde?->rechnung_email)
                        ->placeholder('an Ihre E-Mail-Adresse oben'),

                    TextEntry::make('anzeige_ust_id')
                        ->label('USt-IdNr.')
                        ->state($kunde?->ust_id)
                        ->placeholder('nicht hinterlegt'),

                    TextEntry::make('anzeige_website')
                        ->label('Website')
                        ->state($kunde?->website)
                        ->url($kunde?->website)
                        ->openUrlInNewTab()
                        ->placeholder('nicht hinterlegt'),
                ]),
        ]);
    }

    /** Der Stand in einem Wort — für die Ansicht. */
    private function benachrichtigungsStand(): string
    {
        $nutzer = $this->getUser();

        return match (true) {
            $nutzer->bekommtMailMeldungen() => 'Eingeschaltet',
            $nutzer->wartetAufAdressbestaetigung() => 'Wartet auf Ihre Bestätigung',
            default => 'Aus',
        };
    }

    /** Die gewählten Themen in Worten. */
    private function gewaehlteThemen(): ?string
    {
        $gewaehlt = $this->getUser()->mail_ereignisse;

        $faelle = collect(MailEreignis::fuerKunden())
            ->when($gewaehlt !== null, fn ($f) => $f->filter(
                fn (MailEreignis $e) => in_array($e->value, $gewaehlt, true),
            ));

        return $faelle->isEmpty()
            ? null
            : $faelle->map(fn (MailEreignis $e) => $e->getKundenLabel())->implode(' · ');
    }

    /**
     * Der Knopf, der aus der Ansicht ein Formular macht.
     *
     * Beim erzwungenen Passwortwechsel gibt es ihn nicht: dort ist das
     * Formular der Zweck der Seite, und ein Knopf davor wäre eine Hürde vor
     * der Hürde.
     */
    protected function getHeaderActions(): array
    {
        if ($this->mussWechseln() || $this->bearbeiten) {
            return [];
        }

        return [
            Action::make('bearbeiten')
                ->label('Bearbeiten')
                ->icon('heroicon-o-pencil-square')
                ->action(fn () => $this->modusWechseln(true)),
        ];
    }

    /**
     * Zwischen Ansicht und Formular umschalten.
     *
     * Das Vergessen der zwischengespeicherten Schemas ist der eigentliche
     * Inhalt dieser Methode. Filament baut ein Schema je Anfrage einmal und
     * hält es danach fest — sinnvoll, weil eine Seite es mehrfach abfragt.
     * Hier ist es eine Falle: der Klick auf "Bearbeiten" setzt zwar die
     * Eigenschaft, das schon gebaute Schema bleibt aber die Ansicht, und die
     * Seite sieht danach genauso aus wie vorher. Erst der nächste
     * Seitenaufbau brächte das Formular — was aussieht, als hätte der Knopf
     * nichts getan.
     *
     * "content" muss mit weg, nicht nur "form": darin stecken die Knöpfe
     * unter dem Formular, und die unterscheiden sich zwischen den Modi.
     */
    private function modusWechseln(bool $bearbeiten): void
    {
        $this->bearbeiten = $bearbeiten;

        unset($this->cachedSchemas['form'], $this->cachedSchemas['content']);

        $this->fillForm();
    }

    /**
     * Speichern und Abbrechen nur im Bearbeitungsmodus.
     *
     * Ohne das stünde unter der reinen Ansicht ein Speichern-Knopf, der
     * nichts zu speichern hat — und ein Knopf, der nichts tut, lässt einen
     * am Rest der Seite zweifeln.
     */
    protected function getFormActions(): array
    {
        if (! ($this->bearbeiten || $this->mussWechseln())) {
            return [];
        }

        return parent::getFormActions();
    }

    /**
     * Abbrechen führt zurück in die Ansicht, nicht aus der Seite heraus.
     *
     * Filaments Vorgabe ist der Zurück-Knopf des Panels. Der war richtig,
     * solange die Seite nur ein Formular war; jetzt wäre er die Antwort auf
     * eine Frage, die niemand gestellt hat — wer das Bearbeiten abbricht,
     * will seine Daten sehen und nicht woanders sein. Beim erzwungenen
     * Wechsel bleibt es beim Zurück-Knopf: dort gibt es keine Ansicht,
     * in die man zurückkönnte.
     */
    protected function getCancelFormAction(): Action
    {
        if ($this->mussWechseln()) {
            return parent::getCancelFormAction();
        }

        return Action::make('abbrechen')
            ->label('Abbrechen')
            ->color('gray')
            // Der Wechsel füllt das Formular neu — damit bleiben halb
            // getippte Änderungen nicht stehen und tauchen beim nächsten
            // "Bearbeiten" nicht wieder auf.
            ->action(fn () => $this->modusWechseln(false));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $kunde = $this->kunde();

        if ($kunde !== null) {
            foreach (['name', 'strasse', 'plz', 'ort', 'land', 'rechnung_email', 'ust_id', 'website'] as $feld) {
                $data['kunde_'.$feld] = $kunde->{$feld};
            }
        }

        $data['kontakt_telefon'] = $this->getUser()->kontakt?->telefon;

        // Noch keine Adresse genannt? Dann die Anmeldeadresse vorschlagen —
        // sie ist die naheliegende, und ändern kann er sie im selben Feld.
        $data['benachrichtigungs_email'] ??= $this->getUser()->email;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        // Herausnehmen, nicht bloß ignorieren: was hier stehen bleibt,
        // versucht Filament als Spalte des Nutzers zu speichern.
        foreach ($data as $schluessel => $wert) {
            if (str_starts_with($schluessel, 'kunde_')) {
                $this->kundendaten[substr($schluessel, 6)] = $wert;
                unset($data[$schluessel]);
            }

            if ($schluessel === 'kontakt_telefon') {
                $this->kontaktdaten['telefon'] = $wert;
                unset($data[$schluessel]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->kontaktSpeichern();
        $this->kundendatenSpeichern();
        $this->benachrichtigungenNachziehen();

        // Zurück in die Ansicht. Wer gespeichert hat, will sehen, dass es
        // angekommen ist — und nicht wieder vor demselben Formular stehen.
        if (! $this->mussWechseln()) {
            $this->modusWechseln(false);
        }
    }

    /**
     * Nach dem erzwungenen Passwortwechsel weiter zur Übersicht.
     *
     * Dort ist das Formular eine Hürde vor dem eigentlichen Ziel, und wer
     * nach dem Speichern wieder davor steht, sucht den Ausweg. Im normalen
     * Betrieb gilt das nicht mehr: seit die Seite eine Ansicht hat, landet
     * man nach dem Speichern in genau dieser — mit den eben geänderten
     * Angaben vor sich.
     */
    protected function getRedirectUrl(): ?string
    {
        // Nur nach dem erzwungenen Wechsel. Sonst bleibt die Seite stehen und
        // zeigt die gespeicherten Angaben in der Ansicht — dorthin
        // zurückzuspringen, wo man hergekommen ist, wäre bei einer
        // Anschriftsänderung eine Antwort ohne Bestätigung.
        return $this->kamMitZugeteiltemPasswort ? Uebersicht::getUrl() : null;
    }

    /**
     * Die Telefonnummer landet am Kontakt, nicht am Zugang.
     *
     * Gibt es noch keinen verknüpften Kontakt, entsteht er hier — mit dem
     * Namen aus dem Zugang. Das ist der Weg, auf dem die Kontaktliste eines
     * Kunden von selbst voller wird, ohne dass jemand von uns sie pflegt.
     */
    private function kontaktSpeichern(): void
    {
        $nutzer = $this->getUser();
        $telefon = $this->kontaktdaten['telefon'] ?? null;
        $kontakt = $nutzer->kontakt;

        if ($kontakt === null) {
            // Keinen leeren Kontakt anlegen, nur weil jemand auf Speichern
            // gedrückt hat.
            if (blank($telefon)) {
                return;
            }

            $kontakt = Kontakt::create([
                'customer_id' => $nutzer->customer_id,
                'name' => $nutzer->name,
                'email' => $nutzer->email,
                'telefon' => $telefon,
            ]);

            $nutzer->forceFill(['kontakt_id' => $kontakt->getKey()])->save();

            return;
        }

        $kontakt->update([
            'telefon' => $telefon,
            // Name und Mail ziehen mit: sie stehen an zwei Stellen, und die
            // eben bearbeitete ist die, die stimmt.
            'name' => $nutzer->name,
            'email' => $nutzer->email,
        ]);
    }

    /**
     * Was nach dem Speichern mit der Benachrichtigungsadresse passiert.
     *
     * Drei Fälle, und der mittlere ist der, um den es geht:
     *
     *  - Adresse geändert: die alte Bestätigung ist wertlos, sie fällt weg
     *    und wir schicken eine neue Anfrage. Sonst bekäme eine Adresse Post,
     *    die nie jemand belegt hat.
     *  - Abgeschaltet: die Bestätigung bleibt stehen. Wer später wieder
     *    einschaltet, muss dieselbe Adresse nicht erneut belegen.
     *  - Eingeschaltet, Adresse unverändert und schon bestätigt: nichts tun.
     *
     * Dass er die Frage gesehen hat, halten wir in jedem Fall fest — sonst
     * stünde der Hinweis in seinem Bereich auch bei jemandem, der sich
     * bewusst dagegen entschieden hat.
     */
    private function benachrichtigungenNachziehen(): void
    {
        $nutzer = $this->getUser();

        if (! $nutzer->istKunde()) {
            return;
        }

        $neu = ['benachrichtigungen_gefragt_at' => $nutzer->benachrichtigungen_gefragt_at ?? now()];

        if ($nutzer->wasChanged('benachrichtigungs_email')) {
            $neu['benachrichtigungs_email_bestaetigt_at'] = null;
        }

        $nutzer->forceFill($neu)->save();

        if ($nutzer->wartetAufAdressbestaetigung()) {
            Adressbestaetigung::anfordern($nutzer);

            Notification::make()
                ->title('Bitte bestätigen Sie Ihre Adresse')
                ->body('Wir haben Ihnen einen Link an '.$nutzer->benachrichtigungs_email.' geschickt. Erst danach schreiben wir Ihnen.')
                ->info()
                ->persistent()
                ->send();
        }
    }

    private function kundendatenSpeichern(): void
    {
        $kunde = $this->kunde();

        // Zweite Verteidigungslinie. ->disabled() im Formular verhindert die
        // Eingabe, nicht die Anfrage — wer sie nachbaut, käme sonst durch.
        if (! $this->darfStammdaten()) {
            return;
        }

        if ($kunde === null || $this->kundendaten === []) {
            return;
        }

        // "name" kam als deaktiviertes Feld gar nicht erst mit; die Zeile
        // steht trotzdem da, weil ein späteres Feld sonst still durchrutschen
        // könnte. Erlaubt ist ausschließlich, was der Kunde über sich selbst
        // besser weiß.
        $erlaubt = array_intersect_key($this->kundendaten, array_flip([
            'strasse', 'plz', 'ort', 'land', 'rechnung_email', 'ust_id', 'website',
        ]));

        $kunde->fill($erlaubt);

        if (! $kunde->isDirty()) {
            return;
        }

        $geaendert = array_keys($kunde->getDirty());
        $kunde->save();

        Benachrichtigung::anZustaendige(
            $kunde->getKey(),
            Notification::make()
                ->title($kunde->name.' hat Stammdaten geändert')
                ->body($this->getUser()->name.' hat '.implode(', ', $geaendert).' angepasst.')
                ->icon('heroicon-o-pencil-square'),
            MailEreignis::Stammdaten,
        );
    }

    private function kunde(): ?Customer
    {
        return $this->getUser()->customer;
    }
}
