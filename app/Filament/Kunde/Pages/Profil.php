<?php

namespace App\Filament\Kunde\Pages;

use App\Models\Customer;
use App\Models\Kontakt;
use App\Support\Benachrichtigung;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
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
    }

    /**
     * Nach dem Speichern zurück auf die Übersicht.
     *
     * Filament bleibt von sich aus auf dem Formular stehen. Das ist in einer
     * Verwaltungsoberfläche richtig, wo man mehrere Datensätze nacheinander
     * bearbeitet — hier aber nicht: sein Konto füllt ein Kunde einmal aus und
     * will danach dorthin, wo er hergekommen ist. Besonders deutlich beim
     * erzwungenen Passwortwechsel: dort ist das Formular eine Hürde vor dem
     * eigentlichen Ziel, und wer nach dem Speichern wieder davor steht, sucht
     * den Ausweg.
     */
    protected function getRedirectUrl(): ?string
    {
        return Uebersicht::getUrl();
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
        );
    }

    private function kunde(): ?Customer
    {
        return $this->getUser()->customer;
    }
}
