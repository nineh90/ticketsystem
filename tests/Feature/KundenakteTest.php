<?php

namespace Tests\Feature;

use App\Enums\ProjektPhase;
use App\Enums\Rolle;
use App\Filament\AvatarProviders\InitialenAvatar;
use App\Filament\Kunde\Pages\Profil;
use App\Filament\Kunde\Pages\Uebersicht;
use App\Filament\Kunde\Pages\Zugaenge;
use App\Filament\Kunde\Resources\Projekte\Pages\ViewProjekt;
use App\Filament\Kunde\Widgets\Willkommen;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\KontakteRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ZugangsdatenRelationManager as KundenZugangsdatenRelationManager;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\MeilensteineRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ZugangsdatenRelationManager as ProjektZugangsdatenRelationManager;
use App\Models\Customer;
use App\Models\Kontakt;
use App\Models\Meilenstein;
use App\Models\Project;
use App\Models\User;
use App\Models\Zugangsdaten;
use App\Support\MeilensteinVorlagen;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die Kundenakte: Stammdaten, Kontakte, Zugangsdaten, Meilensteine.
 *
 * Wie im KundenbereichTest liegt der Schwerpunkt auf dem, was ein Kunde
 * NICHT sehen darf. Beim Zugangsdaten-Tresor ist das besonders scharf: hier
 * liegen fremde Passwörter, und ein Fehler in der Sichtbarkeit gibt nicht
 * eine Information preis, sondern einen Zugang.
 */
class KundenakteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    private function kunde(?Customer $customer = null): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => ($customer ?? Customer::factory()->create())->getKey(),
        ]);
    }

    // --- Zugangsdaten-Tresor -------------------------------------------

    public function test_kunde_sieht_nur_freigegebene_zugangsdaten(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);

        $fuerIhn = Zugangsdaten::create([
            'customer_id' => $customer->getKey(),
            'bezeichnung' => 'WordPress',
            'passwort' => 'kunde-darf-das',
            'kunden_sichtbar' => true,
        ]);

        $nurIntern = Zugangsdaten::create([
            'customer_id' => $customer->getKey(),
            'bezeichnung' => 'SFTP',
            'passwort' => 'niemals-nach-aussen',
            'kunden_sichtbar' => false,
        ]);

        $sichtbar = Zugangsdaten::query()->sichtbarFuer($kunde)->pluck('id');

        $this->assertTrue($sichtbar->contains($fuerIhn->getKey()));
        $this->assertFalse($sichtbar->contains($nurIntern->getKey()));
    }

    public function test_kunde_sieht_keine_zugangsdaten_fremder_kunden(): void
    {
        $kunde = $this->kunde();

        $fremd = Zugangsdaten::create([
            'customer_id' => Customer::factory()->create()->getKey(),
            'bezeichnung' => 'Fremdes WordPress',
            'passwort' => 'geheim',
            // Ausdrücklich freigegeben — freigegeben heißt "für SEINEN
            // Kunden", nicht "für alle Kunden".
            'kunden_sichtbar' => true,
        ]);

        $this->assertFalse(
            Zugangsdaten::query()->sichtbarFuer($kunde)->pluck('id')->contains($fremd->getKey()),
        );
    }

    public function test_zugangsdaten_eines_verborgenen_projekts_bleiben_verborgen(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);

        // Der Fall, den man beim Umlegen von kunden_sichtbar am Projekt
        // übersieht: das Projekt verschwindet aus seiner Liste, der Login zu
        // dessen Vorschau stünde aber weiter im Tresor.
        $verborgen = Project::factory()->create([
            'customer_id' => $customer->getKey(),
            'kunden_sichtbar' => false,
        ]);

        $eintrag = Zugangsdaten::create([
            'customer_id' => $customer->getKey(),
            'project_id' => $verborgen->getKey(),
            'bezeichnung' => 'Vorschau-Login',
            'passwort' => 'geheim',
            'kunden_sichtbar' => true,
        ]);

        $this->assertFalse(
            Zugangsdaten::query()->sichtbarFuer($kunde)->pluck('id')->contains($eintrag->getKey()),
        );
    }

    public function test_passwort_liegt_verschluesselt_in_der_datenbank(): void
    {
        $eintrag = Zugangsdaten::create([
            'customer_id' => Customer::factory()->create()->getKey(),
            'bezeichnung' => 'WordPress',
            'passwort' => 'Muschel-Laterne-47',
        ]);

        $roh = \DB::table('zugangsdaten')->where('id', $eintrag->getKey())->value('passwort');

        $this->assertNotSame('Muschel-Laterne-47', $roh);
        $this->assertStringNotContainsString('Muschel', (string) $roh);
        $this->assertSame('Muschel-Laterne-47', $eintrag->fresh()->passwort);
    }

    public function test_ein_unlesbares_passwort_reisst_die_seite_nicht_mit(): void
    {
        // Genau der Fall aus der Entwicklung: die lokale Datenbank ist eine
        // Kopie der Live-Daten, der APP_KEY ist ein anderer. Vorher starb
        // dabei die ganze Projektseite mit "The MAC is invalid" — ein
        // Projekt, das sich nicht mehr öffnen ließ, wegen eines Feldes.
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);

        $projekt = Project::factory()->create(['customer_id' => $customer->getKey()]);

        $eintrag = Zugangsdaten::create([
            'customer_id' => $customer->getKey(),
            'project_id' => $projekt->getKey(),
            'bezeichnung' => 'WordPress',
            'benutzername' => 'redaktion',
            'passwort' => 'mit-dem-alten-schluessel',
            'kunden_sichtbar' => true,
        ]);

        // Geheimtext von einem fremden Schlüssel unterschieben.
        $fremd = new Encrypter(random_bytes(32), config('app.cipher'));
        \DB::table('zugangsdaten')
            ->where('id', $eintrag->getKey())
            ->update(['passwort' => $fremd->encryptString('unlesbar')]);

        $frisch = $eintrag->fresh();

        $this->assertNull($frisch->passwort, 'Unlesbar muss null ergeben, nicht eine Ausnahme.');
        $this->assertTrue($frisch->passwortUnlesbar());

        // Und beide Seiten, auf denen er steht, gehen weiterhin auf.
        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Zugaenge::class)
            ->assertOk()
            ->assertSee('WordPress');

        // Die Projektseite war die, die es zerlegt hat: der Tresor steht dort
        // in einem eigenen Abschnitt, und das Projekt ließ sich deswegen gar
        // nicht mehr öffnen.
        Livewire::test(ViewProjekt::class, ['record' => $projekt->getKey()])
            ->assertOk();
    }

    public function test_leeres_passwort_ist_nicht_dasselbe_wie_unlesbar(): void
    {
        $eintrag = Zugangsdaten::create([
            'customer_id' => Customer::factory()->create()->getKey(),
            'bezeichnung' => 'Zugang liegt beim Kunden',
        ]);

        $this->assertNull($eintrag->passwort);
        $this->assertFalse($eintrag->passwortUnlesbar());
    }

    public function test_neuer_eintrag_ist_ohne_zutun_nicht_kundensichtbar(): void
    {
        $eintrag = Zugangsdaten::create([
            'customer_id' => Customer::factory()->create()->getKey(),
            'bezeichnung' => 'Irgendwas',
        ]);

        $this->assertFalse($eintrag->kunden_sichtbar);
    }

    public function test_zugangsdaten_seite_zeigt_nur_freigegebenes(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);

        Zugangsdaten::create([
            'customer_id' => $customer->getKey(),
            'bezeichnung' => 'Redaktionszugang',
            'benutzername' => 'redaktion',
            'passwort' => 'sichtbares-passwort',
            'kunden_sichtbar' => true,
        ]);

        Zugangsdaten::create([
            'customer_id' => $customer->getKey(),
            'bezeichnung' => 'Unser Serverzugang',
            'passwort' => 'internes-passwort',
            'kunden_sichtbar' => false,
        ]);

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Zugaenge::class)
            ->assertOk()
            ->assertSee('Redaktionszugang')
            ->assertSee('sichtbares-passwort')
            ->assertDontSee('Unser Serverzugang')
            ->assertDontSee('internes-passwort');
    }

    public function test_mein_konto_steht_sichtbar_in_der_navigation(): void
    {
        // Filament hängt die Profilseite nur ins Benutzermenü hinter die
        // Initialen. Für den Kunden ist das kein Weg: er kommt selten her,
        // und nach dem erzwungenen Passwortwechsel stünde er ohne sichtbaren
        // Zugang zu seinen eigenen Daten da.
        $kunde = $this->kunde();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        $punkte = collect(Filament::getPanel('kunde')->getNavigationItems())
            ->map(fn ($punkt) => $punkt->getLabel());

        $this->assertContains('Mein Konto', $punkte);
    }

    public function test_ein_hochgeladenes_logo_landet_beim_speichern_in_der_akte(): void
    {
        // Der Weg vom Formular in die Datenbank, einmal ganz durchgespielt.
        // Anlass war ein Logo, das nach dem Hochladen nirgends auftauchte —
        // es lag noch in Livewires Zwischenablage, weil das Formular nicht
        // gespeichert worden war. Dieser Test hält fest, dass es nicht am
        // Speicherweg lag und dort auch künftig nicht liegt.
        Storage::fake('public');

        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel('admin');

        $customer = Customer::factory()->create(['logo' => null]);

        Livewire::test(EditCustomer::class, ['record' => $customer->getKey()])
            ->fillForm(['logo' => [UploadedFile::fake()->image('logo.png', 300, 300)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $customer->refresh();

        $this->assertNotNull($customer->logo);
        $this->assertStringStartsWith('kunden-logos/', $customer->logo);
        Storage::disk('public')->assertExists($customer->logo);
    }

    // --- Logo als Avatar --------------------------------------------------

    public function test_kundenzugang_traegt_das_logo_seines_kunden(): void
    {
        $customer = Customer::factory()->create(['logo' => 'kunden-logos/verein.png']);
        $kunde = $this->kunde($customer);

        $avatar = (new InitialenAvatar)->get($kunde);

        $this->assertStringContainsString('kunden-logos/verein.png', $avatar);
    }

    public function test_das_logo_steht_auch_im_kundendashboard(): void
    {
        // Als Avatar allein wäre es ein Kreis von zwei Zentimetern im
        // Benutzermenü — also an der Stelle, an der niemand hinsieht.
        $customer = Customer::factory()->create([
            'name' => 'KE!N EINZELFALL e.V.',
            'logo' => 'kunden-logos/verein.png',
        ]);

        $kunde = $this->kunde($customer);
        $kunde->forceFill(['name' => 'Tatjana Belmar'])->save();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Willkommen::class)
            ->assertOk()
            ->assertSee('kunden-logos/verein.png', escape: false)
            ->assertSee('Moin, Tatjana')
            ->assertSee('KE!N EINZELFALL e.V.')
            // Unser Name steht neben dem des Kunden. Sein Logo ist auf dieser
            // Seite das größte Bild — ohne diese Zeile läse sich der Bereich
            // wie seiner allein.
            ->assertSee('An Bord von Nils-Digital');
    }

    public function test_ohne_logo_bleibt_die_begruessung_stehen(): void
    {
        // Kein Platzhalterbild: die Zeile trägt sich auch ohne Logo.
        $customer = Customer::factory()->create(['name' => 'Sarah Schweikert', 'logo' => null]);
        $kunde = $this->kunde($customer);

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Willkommen::class)
            ->assertOk()
            ->assertSee('Sarah Schweikert');
    }

    public function test_ohne_logo_bleiben_es_die_initialen(): void
    {
        // Ein leerer Kreis wäre schlechter als ein Kürzel.
        $kunde = $this->kunde(Customer::factory()->create(['logo' => null]));

        $this->assertStringStartsWith('data:image/svg+xml', (new InitialenAvatar)->get($kunde));
    }

    public function test_mitarbeiter_behalten_ihre_initialen(): void
    {
        // Das Logo trägt die Aussage "hier schreibt jemand von dort". Ein
        // Mitarbeiter gehört zu keinem Kunden — er darf keins bekommen, auch
        // nicht versehentlich über eine customer_id.
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'customer_id' => Customer::factory()->create(['logo' => 'kunden-logos/verein.png'])->getKey(),
        ]);

        $this->assertStringStartsWith('data:image/svg+xml', (new InitialenAvatar)->get($mitarbeiter));
    }

    // --- Die Glocke ------------------------------------------------------

    public function test_beide_panels_fragen_im_selben_takt_nach(): void
    {
        // Die Glocke ist das Einzige, was eine Meldung von selbst sichtbar
        // macht. Läuft sie in einem Panel in einem anderen Takt als im
        // anderen, merkt das niemand — man wundert sich nur, warum es
        // "drüben" schneller geht.
        $takt = config('benachrichtigungen.glocke_takt');

        $this->assertNotNull($takt);

        foreach (['admin', 'kunde'] as $panel) {
            $this->assertSame(
                $takt,
                Filament::getPanel($panel)->getDatabaseNotificationsPollingInterval(),
                'Panel '.$panel.' fragt in einem anderen Takt nach.',
            );
        }
    }

    // --- Kundenzugänge sind kein Personal --------------------------------

    public function test_kundenzugaenge_stehen_in_keiner_personalauswahl(): void
    {
        // Der Fehler, der auffiel, als der zweite Kundenzugang entstand: in
        // "Zuständig" und in den Team-Listen standen die Kundinnen mit
        // Namen — man konnte ein Ticket an die Person zuweisen, die es
        // gemeldet hatte.
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);

        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $ausgeschieden = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'aktiv' => false,
        ]);

        $auswahl = User::query()->intern()->pluck('id');

        $this->assertTrue($auswahl->contains($mitarbeiter->getKey()));
        $this->assertFalse($auswahl->contains($kunde->getKey()), 'Ein Kundenzugang steht in der Personalauswahl.');
        $this->assertFalse($auswahl->contains($ausgeschieden->getKey()));
    }

    public function test_jede_personalauswahl_geht_ueber_denselben_scope(): void
    {
        // Damit die Regel nicht an einer sechsten Stelle wieder fehlt: alle
        // Auswahllisten, die Nutzer anbieten, müssen intern() verwenden.
        $dateien = [
            'app/Filament/Resources/Tickets/Schemas/TicketForm.php',
            'app/Filament/Resources/Tickets/Tables/TicketsTable.php',
            'app/Filament/Resources/Projects/Schemas/ProjectForm.php',
            'app/Filament/Resources/Projects/RelationManagers/TicketsRelationManager.php',
            'app/Filament/Resources/Customers/Schemas/CustomerForm.php',
        ];

        foreach ($dateien as $datei) {
            $inhalt = file_get_contents(base_path($datei));

            $this->assertStringContainsString(
                'intern()',
                $inhalt,
                $datei.' bietet Nutzer zur Auswahl an, ohne intern() zu benutzen.',
            );
        }
    }

    // --- Erreichbarkeit über die Oberfläche -----------------------------

    /**
     * Die beiden Formulare lassen sich mit einem bestehenden Datensatz
     * öffnen.
     *
     * Klingt banal, war es nicht: der Hilfetext am Phasenfeld rief
     * ProjektPhase::from($state) auf. Beim Anlegen ist $state ein String und
     * alles ging gut — beim Bearbeiten liefert Filament das bereits
     * gecastete Enum, und die Seite starb mit einem TypeError. Sämtliche
     * Tests waren grün, weil keiner ein Formular mit Daten geöffnet hat.
     */
    public function test_die_formulare_lassen_sich_mit_daten_oeffnen(): void
    {
        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel('admin');

        $projekt = Project::factory()->create(['phase' => ProjektPhase::Abnahme]);

        Livewire::test(EditProject::class, ['record' => $projekt->getKey()])
            ->assertOk();

        Livewire::test(EditCustomer::class, ['record' => $projekt->customer_id])
            ->assertOk();
    }

    public function test_die_neuen_reiter_sind_auch_registriert(): void
    {
        // Der Fehler, den dieser Test verhindert: eine Relation, die es als
        // Klasse gibt, aber in getRelations() fehlt. Sie ist dann fertig
        // gebaut, getestet — und über die Oberfläche nicht erreichbar. Von
        // außen sieht das aus, als gäbe es die Funktion gar nicht.
        $amProjekt = ProjectResource::getRelations();

        $this->assertContains(MeilensteineRelationManager::class, $amProjekt);
        $this->assertContains(ProjektZugangsdatenRelationManager::class, $amProjekt);

        $amKunden = CustomerResource::getRelations();

        $this->assertContains(KontakteRelationManager::class, $amKunden);
        $this->assertContains(KundenZugangsdatenRelationManager::class, $amKunden);
    }

    // --- Fortschritt und Meilensteine ----------------------------------

    public function test_fortschritt_rechnet_nur_kundensichtbare_meilensteine(): void
    {
        $projekt = Project::factory()->create();

        Meilenstein::create(['project_id' => $projekt->getKey(), 'titel' => 'Eins', 'erledigt_at' => now()]);
        Meilenstein::create(['project_id' => $projekt->getKey(), 'titel' => 'Zwei']);

        // Zählt nicht mit — weder oben noch unten im Bruch.
        Meilenstein::create([
            'project_id' => $projekt->getKey(),
            'titel' => 'Altsystem abschalten',
            'kunden_sichtbar' => false,
        ]);

        $this->assertSame(50, $projekt->fortschritt());
    }

    public function test_ohne_meilensteine_gibt_es_keinen_fortschritt(): void
    {
        // null und nicht 0: "wird hier nicht nachgehalten" ist etwas anderes
        // als "noch nichts geschafft", und nur beim ersten darf gar kein
        // Balken erscheinen.
        $this->assertNull(Project::factory()->create()->fortschritt());
    }

    // --- Reihenfolge ---------------------------------------------------

    public function test_neue_meilensteine_haengen_sich_hinten_an(): void
    {
        // Der Fehler dahinter: bekommt jeder neue Punkt die 0 aus der
        // Spaltenvorgabe, sortiert die Liste nach einem Feld, in dem überall
        // dasselbe steht — und die Reihenfolge wird dem Zufall überlassen.
        $projekt = Project::factory()->create();

        $eins = Meilenstein::create(['project_id' => $projekt->getKey(), 'titel' => 'Erstgespräch']);
        $zwei = Meilenstein::create(['project_id' => $projekt->getKey(), 'titel' => 'Angebot']);
        $drei = Meilenstein::create(['project_id' => $projekt->getKey(), 'titel' => 'Livegang']);

        $this->assertSame([1, 2, 3], [$eins->sortierung, $zwei->sortierung, $drei->sortierung]);
    }

    public function test_die_zaehlung_laeuft_je_projekt(): void
    {
        // Sonst wandert der erste Meilenstein eines neuen Projekts ans Ende
        // einer Zählung, die einem anderen Kunden gehört.
        $eins = Project::factory()->create();
        $zwei = Project::factory()->create();

        Meilenstein::create(['project_id' => $eins->getKey(), 'titel' => 'A']);
        Meilenstein::create(['project_id' => $eins->getKey(), 'titel' => 'B']);

        $erster = Meilenstein::create(['project_id' => $zwei->getKey(), 'titel' => 'C']);

        $this->assertSame(1, $erster->sortierung);
    }

    public function test_eine_gesetzte_sortierung_bleibt_stehen(): void
    {
        $projekt = Project::factory()->create();

        $meilenstein = Meilenstein::create([
            'project_id' => $projekt->getKey(),
            'titel' => 'Von Hand einsortiert',
            'sortierung' => 7,
        ]);

        $this->assertSame(7, $meilenstein->sortierung);
    }

    // --- Vorlagen ------------------------------------------------------

    public function test_vorlage_legt_die_punkte_in_ihrer_reihenfolge_an(): void
    {
        $projekt = Project::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(MeilensteineRelationManager::class, [
                'ownerRecord' => $projekt,
                'pageClass' => EditProject::class,
            ])
            // ->table(): "Aus Vorlage" ist eine Kopfaktion der TABELLE, keine
            // Aktion der Komponente — ohne den Kontext findet der Test sie
            // nicht, genau wie im Browser.
            ->callAction(TestAction::make('ausVorlage')->table(), data: [
                'vorlage' => 'website',
                'punkte' => ['Erstgespräch', 'Planung', 'Webseite ist Live'],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(
            ['Erstgespräch', 'Planung', 'Webseite ist Live'],
            $projekt->meilensteine()->inReihenfolge()->pluck('titel')->all(),
        );
    }

    public function test_vorlage_haengt_an_bestehende_an_statt_sie_zu_ersetzen(): void
    {
        // Der Fall, für den der Knopf taugen muss: ein Projekt, an dem schon
        // von Hand gearbeitet wurde. Was dasteht, bleibt vorn.
        $projekt = Project::factory()->create();

        Meilenstein::create(['project_id' => $projekt->getKey(), 'titel' => 'Kickoff vor Ort']);

        Livewire::actingAs($this->admin())
            ->test(MeilensteineRelationManager::class, [
                'ownerRecord' => $projekt,
                'pageClass' => EditProject::class,
            ])
            ->callAction(TestAction::make('ausVorlage')->table(), data: [
                'vorlage' => 'website',
                'punkte' => ['Webseite ist Live'],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(
            ['Kickoff vor Ort', 'Webseite ist Live'],
            $projekt->meilensteine()->inReihenfolge()->pluck('titel')->all(),
        );
    }

    public function test_vorlage_erkennt_bereits_angelegte_punkte(): void
    {
        // Titel, die von Hand entstanden sind, treffen die der Vorlage selten
        // Wort für Wort — der erste Punkt trägt den Kundennamen, ein anderer
        // ist knapper getippt. Sie müssen trotzdem als "steht schon da"
        // durchgehen, sonst schlägt der Knopf einem Kunden ein zweites
        // Angebot in den Zeitstrahl.
        $projekt = Project::factory()->create();

        foreach (['Erstgespräch KE!N EINZELFALL e.V.', 'Angebot', 'Design Vorschlag'] as $titel) {
            Meilenstein::create(['project_id' => $projekt->getKey(), 'titel' => $titel]);
        }

        foreach (['Erstgespräch', 'Erstellung eines Angebots', 'Unser Design Vorschlag'] as $ausDerVorlage) {
            $this->assertTrue(
                MeilensteinVorlagen::stehtSchonDa($projekt, $ausDerVorlage),
                "'{$ausDerVorlage}' hätte als vorhanden erkannt werden müssen.",
            );
        }

        // Was fehlt, bleibt vorgeschlagen.
        $this->assertFalse(MeilensteinVorlagen::stehtSchonDa($projekt, 'Webseite ist Live'));
    }

    public function test_aus_der_vorlage_angelegte_punkte_bleiben_frei_aenderbar(): void
    {
        // Der Punkt, an dem eine Vorlage kippen könnte: sie ist eine Hilfe
        // beim Anlegen und darf danach nichts mehr zu sagen haben. Jeder
        // Kunde bekommt seinen eigenen Zeitstrahl — umbenannt, umsortiert,
        // mit eigenen Terminen und einzeln verborgen. Nichts davon hängt
        // hinterher noch an config/meilensteine.php.
        $projekt = Project::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(MeilensteineRelationManager::class, [
                'ownerRecord' => $projekt,
                'pageClass' => EditProject::class,
            ])
            ->callAction(TestAction::make('ausVorlage')->table(), data: [
                'vorlage' => 'website',
                'punkte' => ['Erstgespräch', 'Planung', 'Webseite ist Live'],
            ])
            ->assertHasNoActionErrors();

        $meilensteine = $projekt->meilensteine()->inReihenfolge()->get();

        // Alles am einzelnen Punkt lässt sich nachträglich ändern.
        $meilensteine[1]->update([
            'titel' => 'Planung mit dem Vorstand',
            'beschreibung' => 'Eigener Text, nicht der aus der Vorlage.',
            'faellig_am' => '2026-09-01',
            'kunden_sichtbar' => false,
        ]);

        // Und die Reihenfolge lässt sich umstellen — hier von Hand, im
        // Browser durch Ziehen.
        $meilensteine[2]->update(['sortierung' => 1]);
        $meilensteine[0]->update(['sortierung' => 3]);

        $this->assertSame(
            ['Webseite ist Live', 'Planung mit dem Vorstand', 'Erstgespräch'],
            $projekt->meilensteine()->inReihenfolge()->pluck('titel')->all(),
        );

        $geaendert = $meilensteine[1]->fresh();

        $this->assertSame('Eigener Text, nicht der aus der Vorlage.', $geaendert->beschreibung);
        $this->assertFalse($geaendert->kunden_sichtbar);
        $this->assertSame('01.09.2026', $geaendert->faellig_am->format('d.m.Y'));

        // Ein einzelner Punkt lässt sich auch wieder entfernen, ohne dass die
        // Vorlage ihn nachschiebt.
        $geaendert->delete();

        $this->assertSame(2, $projekt->meilensteine()->count());
    }

    public function test_zwei_kunden_bekommen_unabhaengige_zeitstrahlen(): void
    {
        // Dieselbe Vorlage, zwei Projekte — was beim einen geändert wird,
        // darf beim anderen nichts bewegen.
        $eins = Project::factory()->create();
        $zwei = Project::factory()->create();

        foreach ([$eins, $zwei] as $projekt) {
            Livewire::actingAs($this->admin())
                ->test(MeilensteineRelationManager::class, [
                    'ownerRecord' => $projekt,
                    'pageClass' => EditProject::class,
                ])
                ->callAction(TestAction::make('ausVorlage')->table(), data: [
                    'vorlage' => 'website',
                    'punkte' => ['Erstgespräch', 'Planung'],
                ])
                ->assertHasNoActionErrors();
        }

        $eins->meilensteine()->where('titel', 'Planung')->update([
            'titel' => 'Planung mit dem Vorstand',
            'erledigt_at' => now(),
        ]);

        $this->assertSame(
            ['Erstgespräch', 'Planung mit dem Vorstand'],
            $eins->meilensteine()->inReihenfolge()->pluck('titel')->all(),
        );

        $this->assertSame(
            ['Erstgespräch', 'Planung'],
            $zwei->meilensteine()->inReihenfolge()->pluck('titel')->all(),
        );

        $this->assertSame(50, $eins->fortschritt());
        $this->assertSame(0, $zwei->fortschritt());
    }

    public function test_jede_vorlage_hat_punkte_mit_titel(): void
    {
        // Eine Vorlage, die als Auswahl erscheint und dann nichts anlegt, ist
        // ein Knopf ohne Wirkung — der Tippfehler in der Konfiguration fällt
        // sonst erst beim Kunden auf.
        $this->assertNotEmpty(MeilensteinVorlagen::auswahl());

        foreach (array_keys(MeilensteinVorlagen::auswahl()) as $schluessel) {
            $this->assertNotEmpty(
                MeilensteinVorlagen::punkte($schluessel),
                "Vorlage '{$schluessel}' hat keine brauchbaren Punkte.",
            );
        }

        $this->assertArrayHasKey(MeilensteinVorlagen::vorgabe(), MeilensteinVorlagen::auswahl());
    }

    // --- Die beiden Adressen -------------------------------------------

    public function test_vor_der_veroeffentlichung_fuehrt_der_knopf_auf_die_vorschau(): void
    {
        $projekt = Project::factory()->create([
            'phase' => ProjektPhase::Abnahme,
            'demo_url' => 'https://vorschau.example.org',
            'live_url' => 'https://example.org',
        ]);

        $this->assertSame('https://vorschau.example.org', $projekt->aktuelleAdresse());
    }

    public function test_ab_live_fuehrt_der_knopf_auf_die_echte_adresse(): void
    {
        $projekt = Project::factory()->create([
            'phase' => ProjektPhase::Live,
            'demo_url' => 'https://vorschau.example.org',
            'live_url' => 'https://example.org',
        ]);

        $this->assertSame('https://example.org', $projekt->aktuelleAdresse());
    }

    public function test_fehlt_die_passende_adresse_nimmt_der_knopf_die_andere(): void
    {
        // Ein Kunde soll nicht vor einer Seite ohne Link stehen, nur weil das
        // Projekt eine Phase weiter ist als die Pflege seiner Felder.
        $projekt = Project::factory()->create([
            'phase' => ProjektPhase::Live,
            'demo_url' => 'https://vorschau.example.org',
            'live_url' => null,
        ]);

        $this->assertSame('https://vorschau.example.org', $projekt->aktuelleAdresse());
    }

    // --- Vorschlag für die Vorschau-Adresse -----------------------------

    public function test_vorschlag_kommt_aus_dem_muster(): void
    {
        config(['demo.muster' => '{projekt}.nils-digital.de']);

        $this->assertSame(
            'https://kein-einzelfall.nils-digital.de',
            Project::vorschauVorschlag('kein-einzelfall'),
        );
    }

    public function test_platzhalter_geht_auch_mitten_in_der_adresse(): void
    {
        // Etwa ein Unterordner statt einer Subdomain.
        config(['demo.muster' => 'https://nils-digital.de/demo/{projekt}']);

        $this->assertSame(
            'https://nils-digital.de/demo/lerndex',
            Project::vorschauVorschlag('lerndex'),
        );
    }

    public function test_vorschlag_vertraegt_protokoll_und_schraegstrich(): void
    {
        // Beides passiert beim Kopieren aus der Adresszeile.
        config(['demo.muster' => 'https://{projekt}.nils-digital.de/']);

        $this->assertSame(
            'https://wg.nils-digital.de',
            Project::vorschauVorschlag('wg'),
        );
    }

    public function test_ohne_muster_gibt_es_keinen_vorschlag(): void
    {
        config(['demo.muster' => null]);

        $this->assertNull(Project::vorschauVorschlag('irgendwas'));
    }

    public function test_ohne_kuerzel_gibt_es_keinen_vorschlag(): void
    {
        config(['demo.muster' => '{projekt}.nils-digital.de']);

        $this->assertNull(Project::vorschauVorschlag(null));
    }

    public function test_der_vorschlag_traegt_sich_nirgends_von_selbst_ein(): void
    {
        // Der Kern der Sache: was der Kunde sieht, ist projects.demo_url —
        // und das ist leer, bis es jemand ausfüllt. Ein Vorschlag, der sich
        // still einträgt, wäre ein Knopf ins Leere.
        config(['demo.muster' => '{projekt}.nils-digital.de']);

        $projekt = Project::factory()->create(['demo_url' => null, 'live_url' => null]);

        $this->assertNotNull(Project::vorschauVorschlag($projekt->slug));
        $this->assertNull($projekt->aktuelleAdresse());
    }

    public function test_entwicklung_auf_der_eigenen_domain_heisst_nicht_vorschau(): void
    {
        // Der Fall ohne Vorschau: wir bauen direkt auf der Domain des Kunden,
        // weil es nichts gibt, was dort noch stünde. Der Knopf führt richtig
        // dorthin — er darf sich dann aber nicht "Vorschau ansehen" nennen.
        $projekt = Project::factory()->create([
            'phase' => ProjektPhase::Umsetzung,
            'demo_url' => null,
            'live_url' => 'https://example.org',
        ]);

        $this->assertSame('https://example.org', $projekt->aktuelleAdresse());
        $this->assertTrue($projekt->zeigtLiveAdresse());
    }

    public function test_mit_vorschau_bleibt_es_eine_vorschau(): void
    {
        $projekt = Project::factory()->create([
            'phase' => ProjektPhase::Umsetzung,
            'demo_url' => 'https://neu.example.org',
            'live_url' => 'https://example.org',
        ]);

        $this->assertFalse($projekt->zeigtLiveAdresse());
    }

    public function test_ohne_jede_adresse_gibt_es_keinen_knopf(): void
    {
        $projekt = Project::factory()->create(['demo_url' => null, 'live_url' => null]);

        $this->assertNull($projekt->aktuelleAdresse());
        $this->assertFalse($projekt->zeigtLiveAdresse());
    }

    // --- Profil: was der Kunde selbst ändern darf ----------------------

    public function test_kunde_pflegt_seine_firmendaten_selbst(): void
    {
        $customer = Customer::factory()->create(['name' => 'KE!N EINZELFALL e.V.']);
        $kunde = $this->kunde($customer);
        // Der Zugang, der beim Kunden für die Stammdaten zuständig ist.
        $kunde->forceFill(['stammdaten_pflegen' => true])->save();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Profil::class)
            // Seit "Mein Konto" im Anzeigemodus startet, liegt das Formular
            // hinter diesem Knopf.
            ->callAction('bearbeiten')
            ->fillForm([
                'kunde_strasse' => 'Hauptstraße 1',
                'kunde_plz' => '30159',
                'kunde_ort' => 'Hannover',
                'kunde_rechnung_email' => 'buchhaltung@example.org',
                'kontakt_telefon' => '0511 123456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $customer->refresh();

        $this->assertSame('Hauptstraße 1', $customer->strasse);
        $this->assertSame('Hannover', $customer->ort);
        $this->assertSame('buchhaltung@example.org', $customer->rechnung_email);

        // Die Telefonnummer gehört an den Kontakt, nicht an den Zugang — und
        // der Kontakt entsteht dabei, wenn es noch keinen gab.
        $this->assertSame('0511 123456', $kunde->fresh()->kontakt?->telefon);
    }

    public function test_kunde_kann_den_firmennamen_nicht_ueberschreiben(): void
    {
        $customer = Customer::factory()->create(['name' => 'KE!N EINZELFALL e.V.']);
        $kunde = $this->kunde($customer);

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        // Am Namen hängen das Kürzel jeder Ticketnummer und die Zuordnung
        // aller Projekte. Das Feld ist deaktiviert; der Test sichert, dass
        // auch eine nachgebaute Anfrage daran nichts ändert.
        Livewire::test(Profil::class)
            // Seit "Mein Konto" im Anzeigemodus startet, liegt das Formular
            // hinter diesem Knopf.
            ->callAction('bearbeiten')
            ->fillForm(['kunde_name' => 'Etwas ganz anderes'])
            ->call('save');

        $this->assertSame('KE!N EINZELFALL e.V.', $customer->fresh()->name);
    }

    // --- Mehrere Zugänge beim selben Kunden ------------------------------

    public function test_beim_pflichtwechsel_steht_nur_das_passwort_da(): void
    {
        // Ein Zugang, dem gerade ein Passwort zugeteilt wurde, soll nicht vor
        // einem Formular mit Anschrift und USt-IdNr. stehen. Gemeint ist nur
        // das Passwort — alles andere liest sich wie eine Aufforderung.
        $kunde = $this->kunde();
        $kunde->forceFill(['passwort_wechseln' => true])->save();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Profil::class)
            ->assertOk()
            ->assertSee('Neues Passwort')
            ->assertDontSee('Ihr Unternehmen')
            ->assertDontSee('USt-IdNr.');
    }

    public function test_nach_dem_wechsel_steht_wieder_alles_da(): void
    {
        $kunde = $this->kunde();
        $kunde->forceFill(['passwort_wechseln' => false, 'stammdaten_pflegen' => true])->save();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Profil::class)
            ->assertOk()
            ->assertSee('Ihr Unternehmen');
    }

    public function test_ohne_recht_aendert_ein_zweiter_zugang_die_firmendaten_nicht(): void
    {
        $customer = Customer::factory()->create(['ort' => 'Hannover']);

        $zweiter = $this->kunde($customer);
        $zweiter->forceFill(['stammdaten_pflegen' => false])->save();

        $this->actingAs($zweiter, 'kunde');
        Filament::setCurrentPanel('kunde');

        // Auch eine nachgebaute Anfrage kommt nicht durch: die Prüfung sitzt
        // im Speichern, nicht nur im deaktivierten Feld.
        Livewire::test(Profil::class)
            // Seit "Mein Konto" im Anzeigemodus startet, liegt das Formular
            // hinter diesem Knopf.
            ->callAction('bearbeiten')
            ->fillForm(['kunde_ort' => 'Berlin'])
            ->call('save');

        $this->assertSame('Hannover', $customer->fresh()->ort);
    }

    public function test_mit_recht_geht_es_weiterhin(): void
    {
        $customer = Customer::factory()->create(['ort' => 'Hannover']);

        $zustaendig = $this->kunde($customer);
        $zustaendig->forceFill(['stammdaten_pflegen' => true])->save();

        $this->actingAs($zustaendig, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Profil::class)
            // Seit "Mein Konto" im Anzeigemodus startet, liegt das Formular
            // hinter diesem Knopf.
            ->callAction('bearbeiten')
            ->fillForm(['kunde_ort' => 'Berlin'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Berlin', $customer->fresh()->ort);
    }

    public function test_jeder_zugang_pflegt_seine_eigene_telefonnummer(): void
    {
        // Was ihm selbst gehört, darf er immer ändern — auch ohne Recht an
        // den Firmendaten. Sonst hinge die Nummer des zweiten
        // Ansprechpartners für immer an uns.
        $customer = Customer::factory()->create();

        $zweiter = $this->kunde($customer);
        $zweiter->forceFill(['stammdaten_pflegen' => false])->save();

        $this->actingAs($zweiter, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Profil::class)
            // Seit "Mein Konto" im Anzeigemodus startet, liegt das Formular
            // hinter diesem Knopf.
            ->callAction('bearbeiten')
            ->fillForm(['kontakt_telefon' => '0511 999'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0511 999', $zweiter->fresh()->kontakt?->telefon);
    }

    // --- Passwortwechsel ------------------------------------------------

    public function test_ein_neues_passwort_muss_zweimal_gleich_eingegeben_werden(): void
    {
        $kunde = $this->kunde();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        // Zwei Felder, weil ein vertipptes Passwort erst beim nächsten
        // Anmelden auffällt — und dann ist der Zugang zu. Ohne Mailversand
        // gibt es an dieser Stelle keinen Weg zurück außer einem Anruf.
        Livewire::test(Profil::class)
            // Seit "Mein Konto" im Anzeigemodus startet, liegt das Formular
            // hinter diesem Knopf.
            ->callAction('bearbeiten')
            ->fillForm([
                'password' => 'Distel-Kompass-Segel-41',
                'passwordConfirmation' => 'Distel-Kompass-Segel-42',
                'currentPassword' => 'password',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_mit_zweimal_demselben_passwort_geht_es_durch(): void
    {
        $kunde = $this->kunde();
        $kunde->forceFill(['passwort_wechseln' => true])->save();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Profil::class)
            ->fillForm([
                'password' => 'Distel-Kompass-Segel-41',
                'passwordConfirmation' => 'Distel-Kompass-Segel-41',
                // UserFactory legt "password" an; das alte Passwort wird
                // verlangt, damit ein offen stehender Rechner nicht genügt,
                // um jemanden auszusperren.
                'currentPassword' => 'password',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            // Nicht auf dem Formular stehen bleiben: nach dem Wechsel will
            // der Kunde dorthin, wo er hinwollte.
            ->assertRedirect(Uebersicht::getUrl(panel: 'kunde'));

        $kunde->refresh();

        $this->assertTrue(Hash::check('Distel-Kompass-Segel-41', $kunde->password));
        // Selbst gewählt heißt: der Zwang ist erledigt.
        $this->assertFalse($kunde->passwort_wechseln);
    }

    public function test_fremd_gesetztes_passwort_verlangt_einen_wechsel(): void
    {
        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $kunde = $this->kunde();

        $this->actingAs($admin, 'web');

        $kunde->update(['password' => Hash::make('vom-admin-vergeben')]);

        $this->assertTrue($kunde->fresh()->passwort_wechseln);
    }

    public function test_wer_sein_passwort_selbst_setzt_muss_nichts_wechseln(): void
    {
        $kunde = $this->kunde();
        $kunde->forceFill(['passwort_wechseln' => true])->save();

        $this->actingAs($kunde, 'kunde');

        $kunde->update(['password' => Hash::make('mein-eigenes-passwort')]);

        $this->assertFalse($kunde->fresh()->passwort_wechseln);
    }

    public function test_auch_das_zweite_zugeteilte_passwort_verlangt_einen_wechsel(): void
    {
        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $kunde = $this->kunde();

        // Der Kunde hat brav gewechselt …
        $this->actingAs($kunde, 'kunde');
        $kunde->update(['password' => Hash::make('mein-eigenes')]);
        $this->assertFalse($kunde->fresh()->passwort_wechseln);

        // … und ruft ein halbes Jahr später an, weil er es vergessen hat.
        $this->actingAs($admin, 'web');
        $kunde->update(['password' => Hash::make('neues-startpasswort')]);

        $this->assertTrue($kunde->fresh()->passwort_wechseln);
    }

    public function test_seeder_und_konsole_loesen_keinen_wechselzwang_aus(): void
    {
        // Ohne angemeldeten Nutzer hat niemand etwas geschenkt bekommen.
        $kunde = $this->kunde();

        $kunde->update(['password' => Hash::make('aus-einem-seeder')]);

        $this->assertFalse($kunde->fresh()->passwort_wechseln);
    }

    public function test_wer_wechseln_muss_landet_auf_dem_profil(): void
    {
        $kunde = $this->kunde();
        $kunde->forceFill(['passwort_wechseln' => true])->save();

        $this->actingAs($kunde, 'kunde');

        $this->get('/kunde')->assertRedirect('/kunde/profile');
        $this->get('/kunde/anliegen')->assertRedirect('/kunde/profile');

        // Das Profil selbst muss erreichbar bleiben, sonst dreht sich die
        // Umleitung im Kreis und der Zugang ist unbenutzbar.
        $this->get('/kunde/profile')->assertOk();
    }

    public function test_ohne_kennzeichen_leitet_nichts_um(): void
    {
        $this->actingAs($this->kunde(), 'kunde');

        $this->get('/kunde')->assertOk();
    }

    // --- Kontakte -------------------------------------------------------

    public function test_hauptkontakt_steht_vor_den_uebrigen(): void
    {
        $customer = Customer::factory()->create();

        Kontakt::create(['customer_id' => $customer->getKey(), 'name' => 'Anna Zweite']);
        $haupt = Kontakt::create([
            'customer_id' => $customer->getKey(),
            'name' => 'Zora Erste',
            'hauptkontakt' => true,
        ]);

        $this->assertTrue($customer->hauptkontakt()->is($haupt));
    }

    public function test_inaktive_kontakte_gelten_nicht_als_hauptkontakt(): void
    {
        $customer = Customer::factory()->create();

        Kontakt::create([
            'customer_id' => $customer->getKey(),
            'name' => 'Ausgeschieden',
            'hauptkontakt' => true,
            'aktiv' => false,
        ]);
        $aktiv = Kontakt::create(['customer_id' => $customer->getKey(), 'name' => 'Noch da']);

        $this->assertTrue($customer->hauptkontakt()->is($aktiv));
    }
}
