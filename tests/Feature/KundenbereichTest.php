<?php

namespace Tests\Feature;

use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Enums\TicketArt;
use App\Filament\Kunde\Pages\Kontakt;
use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use App\Filament\Kunde\Resources\Anliegen\Pages\CreateAnliegen;
use App\Filament\Kunde\Resources\Anliegen\Pages\ListAnliegen;
use App\Filament\Kunde\Resources\Anliegen\Pages\ViewAnliegen;
use App\Filament\Kunde\Resources\Anliegen\RelationManagers\AntwortenRelationManager;
use App\Filament\Kunde\Resources\Projekte\Pages\ListProjekte;
use App\Filament\Widgets\VonKunden;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Der Kundenbereich.
 *
 * Der Schwerpunkt liegt nicht darauf, dass die Seiten aufgehen, sondern
 * darauf, was ein Kunde NICHT sieht und NICHT darf. Das ist die Eigenschaft,
 * die beim Weiterbauen unbemerkt kaputtgeht — eine Seite, die nicht mehr
 * lädt, fällt beim ersten Blick auf; ein Ticket eines fremden Kunden in einer
 * Liste fällt niemandem auf, weil die Liste ja etwas anzeigt.
 */
class KundenbereichTest extends TestCase
{
    use RefreshDatabase;

    private function kunde(?Customer $customer = null): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => ($customer ?? Customer::factory()->create())->getKey(),
        ]);
    }

    private function stadium(array $eigenschaften = []): TicketStatus
    {
        return TicketStatus::factory()->create($eigenschaften);
    }

    // --- Zugang -------------------------------------------------------

    public function test_kunde_kommt_ins_kundenpanel_aber_nicht_ins_interne(): void
    {
        $kunde = $this->kunde();

        $this->assertTrue($kunde->canAccessPanel(Filament::getPanel('kunde')));
        $this->assertFalse($kunde->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_mitarbeiter_kommt_nicht_ins_kundenpanel(): void
    {
        // Die Trennung gilt in beide Richtungen: im Kundenpanel sähe ein
        // Mitarbeiter die Oberfläche eines Kunden, aber mit seinen eigenen
        // Rechten — und jede Regel darin wäre ab da doppelt zu prüfen.
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $this->assertFalse($mitarbeiter->canAccessPanel(Filament::getPanel('kunde')));
        $this->assertTrue($mitarbeiter->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_kundenzugang_ohne_kunden_kommt_nirgends_hinein(): void
    {
        $ohneKunde = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => null,
        ]);

        $this->assertFalse($ohneKunde->canAccessPanel(Filament::getPanel('kunde')));
        $this->assertFalse($ohneKunde->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_ohne_freigabe_kein_zugang(): void
    {
        $gesperrt = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => false,
            'customer_id' => Customer::factory()->create()->getKey(),
        ]);

        $this->assertFalse($gesperrt->canAccessPanel(Filament::getPanel('kunde')));
    }

    public function test_beide_anmeldungen_bestehen_nebeneinander(): void
    {
        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $kunde = $this->kunde();

        // Der Grund für den eigenen Guard: wer intern angemeldet ist, soll
        // den Kundenbereich ansehen können, ohne sich vorher abzumelden.
        // Mit einem gemeinsamen Guard verdrängte die zweite Anmeldung die
        // erste, und der Weg zurück endete in einem 403.
        $this->actingAs($admin, 'web');
        $this->actingAs($kunde, 'kunde');

        $this->get('/')->assertOk();
        $this->get('/kunde')->assertOk();

        $this->assertTrue(auth('web')->user()->is($admin));
        $this->assertTrue(auth('kunde')->user()->is($kunde));
    }

    public function test_abmelden_betrifft_nur_den_genannten_bereich(): void
    {
        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $kunde = $this->kunde();

        $this->actingAs($admin, 'web');
        $this->actingAs($kunde, 'kunde');

        $this->post(route('abmelden', 'intern'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertFalse(auth('web')->check());
        // Der zweite Bereich bleibt angemeldet. Ein session()->invalidate()
        // hätte ihn mitgenommen — das ist der Grund, warum der Controller
        // nur den einen Guard abmeldet.
        $this->assertTrue(auth('kunde')->check());
    }

    public function test_abmelden_kennt_nur_die_beiden_bereiche(): void
    {
        // Aus der URL darf kein beliebiger Guard werden.
        $this->post(route('abmelden', 'web'))->assertNotFound();
    }

    // --- Sichtbarkeit -------------------------------------------------

    public function test_kunde_sieht_nur_die_eigenen_projekte(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);

        $eigenes = Project::factory()->for($customer)->create();
        $fremdes = Project::factory()->create();

        $sichtbar = Project::query()->sichtbarFuer($kunde)->pluck('id');

        $this->assertTrue($sichtbar->contains($eigenes->getKey()));
        $this->assertFalse($sichtbar->contains($fremdes->getKey()));
    }

    public function test_verborgenes_projekt_verschwindet_samt_seiner_tickets(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);

        $verborgen = Project::factory()->for($customer)->create(['kunden_sichtbar' => false]);
        $ticket = Ticket::factory()->for($verborgen, 'project')->create();

        $this->assertFalse(
            Project::query()->sichtbarFuer($kunde)->whereKey($verborgen->getKey())->exists(),
        );

        // Der eigentliche Punkt: das Ticket trägt dieselbe customer_id wie
        // alle anderen. Liefe die Sichtbarkeit darüber statt über das
        // Projekt, käme es hier durch.
        $this->assertFalse(
            Ticket::query()->sichtbarFuer($kunde)->whereKey($ticket->getKey())->exists(),
        );

        $this->assertFalse($kunde->can('view', $ticket));
    }

    public function test_kunde_sieht_alle_tickets_seines_projekts_nicht_nur_die_eigenen(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();

        // Von uns angelegt, nicht vom Kunden gemeldet.
        $unseres = Ticket::factory()->for($projekt, 'project')->create();

        $this->assertTrue(
            Ticket::query()->sichtbarFuer($kunde)->whereKey($unseres->getKey())->exists(),
        );
        $this->assertTrue($kunde->can('view', $unseres));
    }

    public function test_kunde_sieht_keine_internen_kommentare(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();
        $ticket = Ticket::factory()->for($projekt, 'project')->create();

        $intern = Comment::factory()->for($ticket)->create(['ist_intern' => true]);
        $offen = Comment::factory()->for($ticket)->create(['ist_intern' => false]);

        $this->assertFalse($kunde->can('view', $intern));
        $this->assertTrue($kunde->can('view', $offen));

        $fuerKunden = $ticket->comments()->fuerKunden()->pluck('id');
        $this->assertFalse($fuerKunden->contains($intern->getKey()));
        $this->assertTrue($fuerKunden->contains($offen->getKey()));
    }

    public function test_kunde_darf_nichts_aendern(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();
        $ticket = Ticket::factory()->for($projekt, 'project')->create();
        $eigenerKommentar = Comment::factory()->for($ticket)->create([
            'ist_intern' => false,
            'user_id' => $kunde->getKey(),
        ]);

        $this->assertFalse($kunde->can('update', $ticket));
        $this->assertFalse($kunde->can('delete', $ticket));
        $this->assertFalse($kunde->can('update', $projekt));

        // Auch am eigenen Beitrag nicht: ein Verlauf, aus dem nachträglich
        // etwas verschwindet, taugt für keine der beiden Seiten als Beleg.
        $this->assertFalse($kunde->can('update', $eigenerKommentar));
        $this->assertFalse($kunde->can('delete', $eigenerKommentar));
    }

    public function test_kunde_sieht_keine_zeitbuchungen(): void
    {
        $kunde = $this->kunde();

        $this->assertFalse($kunde->can('viewAny', TimeEntry::class));
        $this->assertFalse($kunde->can('viewAny', Customer::class));
    }

    // --- Seiten -------------------------------------------------------

    public function test_die_seiten_des_kundenbereichs_laden(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();
        $ticket = Ticket::factory()->for($projekt, 'project')->create();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        $this->get('/kunde')->assertOk();
        $this->get(AnliegenResource::getUrl('index', panel: 'kunde'))->assertOk();
        $this->get(AnliegenResource::getUrl('create', panel: 'kunde'))->assertOk();
        $this->get(AnliegenResource::getUrl('view', ['record' => $ticket], panel: 'kunde'))->assertOk();
        $this->get(Kontakt::getUrl(panel: 'kunde'))->assertOk();

        Livewire::test(ListProjekte::class)->assertOk();
        Livewire::test(ListAnliegen::class)->assertOk();
    }

    public function test_fremdes_ticket_laesst_sich_nicht_ueber_die_adresse_oeffnen(): void
    {
        $kunde = $this->kunde();
        $fremdes = Ticket::factory()->create();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        // 404 und nicht 403: die Abfrage der Ressource ist bereits auf die
        // eigenen Projekte beschränkt, das fremde Ticket existiert für diesen
        // Zugang also gar nicht. Das ist die bessere der beiden Antworten —
        // ein 403 bestätigt, dass es den Datensatz gibt.
        $this->get(AnliegenResource::getUrl('view', ['record' => $fremdes], panel: 'kunde'))
            ->assertNotFound();
    }

    public function test_anhang_nur_aus_dem_eigenen_projekt(): void
    {
        Storage::fake(Attachment::PLATTE);

        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();

        $eigenes = Ticket::factory()->for($projekt, 'project')->create();
        $fremdes = Ticket::factory()->create();

        $meiner = $this->anhangAn($eigenes);
        $fremder = $this->anhangAn($fremdes);

        $this->actingAs($kunde, 'kunde');

        // Die Auslieferung hängt an der AttachmentPolicy, und die fragt die
        // Rechte am Ticket ab. Genau deshalb liegen die Dateien außerhalb von
        // public/ — sonst gäbe Apache sie an jeden heraus, der die Adresse
        // kennt.
        $this->get(route('kunde.anhang.zeigen', $meiner))->assertOk();
        $this->get(route('kunde.anhang.zeigen', $fremder))->assertForbidden();
    }

    private function anhangAn(Ticket $ticket): Attachment
    {
        $pfad = 'anhaenge/'.$ticket->id.'/abc123__screenshot.png';
        Storage::disk(Attachment::PLATTE)->put($pfad, 'nicht-wirklich-ein-bild');

        return $ticket->attachments()->create([
            'pfad' => $pfad,
            'dateiname' => 'screenshot.png',
            'mime' => 'image/png',
            'groesse' => 23,
        ]);
    }

    // --- Melden -------------------------------------------------------

    public function test_kunde_meldet_ein_anliegen_und_es_landet_richtig(): void
    {
        $backlog = $this->stadium(['sortierung' => 1]);
        $this->stadium(['sortierung' => 2]);

        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(CreateAnliegen::class)
            ->fillForm([
                'art' => TicketArt::Fehler->value,
                'project_id' => $projekt->getKey(),
                'titel' => 'Das Kontaktformular verschickt nichts',
                'beschreibung' => 'Nach dem Absenden passiert nichts.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = Ticket::query()->latest('id')->first();

        $this->assertSame('Das Kontaktformular verschickt nichts', $ticket->titel);
        $this->assertSame(TicketArt::Fehler, $ticket->art);
        // Herkunft und Stadium werden gesetzt, nicht gewählt.
        $this->assertSame(Quelle::Kunde, $ticket->quelle);
        $this->assertSame($backlog->getKey(), $ticket->ticket_status_id);
        $this->assertSame($kunde->getKey(), $ticket->created_by);
        $this->assertSame($customer->getKey(), $ticket->customer_id);
    }

    public function test_screenshots_koennen_gleich_beim_melden_mitgeschickt_werden(): void
    {
        Storage::fake(Attachment::PLATTE);

        $this->stadium(['sortierung' => 1]);

        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(CreateAnliegen::class)
            ->fillForm([
                'art' => TicketArt::Fehler->value,
                'project_id' => $projekt->getKey(),
                'titel' => 'Galerie bleibt leer',
                'dateien' => [UploadedFile::fake()->image('screenshot.png')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = Ticket::query()->latest('id')->first();
        $anhang = $ticket->attachments()->first();

        $this->assertNotNull($anhang);
        $this->assertSame('screenshot.png', $anhang->dateiname);
        $this->assertSame($kunde->getKey(), $anhang->user_id);

        // Aus dem Zwischenlager in den Ordner des Anliegens verschoben —
        // dieselbe Ablage wie bei intern hochgeladenen Anhängen.
        $this->assertStringStartsWith('anhaenge/'.$ticket->getKey().'/', $anhang->pfad);
        Storage::disk(Attachment::PLATTE)->assertExists($anhang->pfad);
    }

    public function test_liegengebliebene_uploads_werden_weggeraeumt(): void
    {
        Storage::fake(Attachment::PLATTE);

        $this->stadium(['sortierung' => 1]);
        $kunde = $this->kunde();

        $platte = Storage::disk(Attachment::PLATTE);
        $platte->put('anhaenge/eingang/alt__screenshot.png', 'alt');
        $platte->put('anhaenge/eingang/neu__screenshot.png', 'neu');

        // Die ältere Datei stammt aus einem Formular, das nie abgeschickt
        // wurde. Sie gehört zu nichts mehr und enthält womöglich genau das,
        // was weg sollte.
        touch($platte->path('anhaenge/eingang/alt__screenshot.png'), now()->subDays(2)->getTimestamp());

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(CreateAnliegen::class)->assertOk();

        $platte->assertMissing('anhaenge/eingang/alt__screenshot.png');
        // Ein frischer Upload gehört möglicherweise zu einem Formular, das
        // gerade ausgefüllt wird — den darf das Aufräumen nicht mitnehmen.
        $platte->assertExists('anhaenge/eingang/neu__screenshot.png');
    }

    public function test_vorgaben_aus_der_adresse_werden_uebernommen_aber_geprueft(): void
    {
        $this->stadium(['sortierung' => 1]);

        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $eigenes = Project::factory()->for($customer)->create();
        $fremdes = Project::factory()->create();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::withQueryParams(['art' => TicketArt::Frage->value, 'project_id' => $eigenes->getKey()])
            ->test(CreateAnliegen::class)
            ->assertFormSet([
                'art' => TicketArt::Frage->value,
                'project_id' => $eigenes->getKey(),
            ]);

        // Ein fremdes Projekt in der Adresse wird verworfen. Stehen bleibt,
        // was das Formular ohnehin vorschlägt — hier das einzige eigene
        // Projekt, nicht das fremde aus der Adresse.
        Livewire::withQueryParams(['project_id' => $fremdes->getKey()])
            ->test(CreateAnliegen::class)
            ->assertFormSet(['project_id' => $eigenes->getKey()]);
    }

    public function test_kunde_kann_kein_anliegen_in_fremdem_projekt_anlegen(): void
    {
        $this->stadium(['sortierung' => 1]);

        $kunde = $this->kunde();
        $fremdesProjekt = Project::factory()->create();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        // Die Auswahlliste zeigt es gar nicht an; hier wird der Fall
        // geprüft, in dem jemand die Anfrage selbst zusammenbaut.
        Livewire::test(CreateAnliegen::class)
            ->fillForm([
                'art' => TicketArt::Fehler->value,
                'project_id' => $fremdesProjekt->getKey(),
                'titel' => 'Sollte nicht durchkommen',
            ])
            ->call('create')
            ->assertHasFormErrors(['project_id']);

        $this->assertDatabaseMissing('tickets', ['titel' => 'Sollte nicht durchkommen']);
    }

    public function test_antwort_des_kunden_ist_niemals_intern(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();
        $ticket = Ticket::factory()->for($projekt, 'project')->create();

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(AntwortenRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => ViewAnliegen::class,
        ])
            // callTableAction, nicht callAction: der Knopf hängt an der
            // Tabelle des Relation Managers, nicht an der Seite.
            ->callTableAction('create', data: ['body' => 'Hier ist der Screenshot.'])
            ->assertHasNoTableActionErrors();

        $antwort = $ticket->comments()->latest('id')->first();

        // ist_intern ist am Model auf true vorbelegt. Bliebe es dabei, wäre
        // die eigene Antwort für den Kunden im selben Moment unsichtbar.
        $this->assertFalse($antwort->ist_intern);
        $this->assertSame($kunde->getKey(), $antwort->user_id);
    }

    // --- Benachrichtigungen -------------------------------------------

    public function test_kundenmeldung_benachrichtigt_die_zustaendigen(): void
    {
        $this->stadium(['sortierung' => 1]);

        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $unbeteiligt = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);

        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();

        Ticket::factory()->for($projekt, 'project')->create([
            'quelle' => Quelle::Kunde,
            'created_by' => $kunde->getKey(),
        ]);

        $this->assertSame(1, $admin->notifications()->count());
        // Ein Mitarbeiter ohne Bezug zu diesem Projekt bekommt nichts — in
        // der Betreffzeile stünde sonst der Name eines fremden Kunden.
        $this->assertSame(0, $unbeteiligt->notifications()->count());
        $this->assertSame(0, $kunde->notifications()->count());
    }

    /**
     * Die Benachrichtigung darf nicht in der Warteschlange hängenbleiben.
     *
     * Der Test stellt die Warteschlange ausdrücklich auf "database" — so wie
     * es produktiv und lokal eingestellt ist. In der Testumgebung steht sie
     * auf "sync" (phpunit.xml), und genau deshalb sah beim ersten Bauen alles
     * richtig aus, während im Browser nie etwas an der Glocke ankam: Filaments
     * DatabaseNotification ist ein ShouldQueue, die Meldungen lagen in der
     * jobs-Tabelle, und einen Worker gibt es in diesem Projekt nicht.
     */
    public function test_benachrichtigung_kommt_auch_ohne_worker_an(): void
    {
        config(['queue.default' => 'database']);

        $this->stadium(['sortierung' => 1]);

        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();

        Ticket::factory()->for($projekt, 'project')->create([
            'quelle' => Quelle::Kunde,
            'created_by' => $kunde->getKey(),
        ]);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_das_dashboard_zeigt_kundenmeldungen_nur_wenn_es_welche_gibt(): void
    {
        $this->stadium(['sortierung' => 1]);

        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $customer = Customer::factory()->create();
        $projekt = Project::factory()->for($customer)->create();

        $this->actingAs($admin, 'web');

        // Ohne offene Kundenmeldung bleibt die Karte weg — eine Liste, die
        // meistens leer dasteht, lernt man zu überblättern.
        $this->assertFalse(VonKunden::canView());

        Ticket::factory()->for($projekt, 'project')->create(['quelle' => Quelle::Kunde]);

        $this->assertTrue(VonKunden::canView());

        Livewire::test(VonKunden::class)
            ->assertOk()
            ->assertCanSeeTableRecords(Ticket::query()->vomKunden()->get());
    }

    public function test_warten_auf_kunde_benachrichtigt_den_kunden(): void
    {
        $offen = $this->stadium(['sortierung' => 1]);
        $wartend = $this->stadium(['sortierung' => 2, 'wartet_auf_kunde' => true]);

        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();

        $ticket = Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $offen->getKey(),
        ]);

        $this->assertSame(0, $kunde->notifications()->count());

        $ticket->update(['ticket_status_id' => $wartend->getKey()]);

        $this->assertSame(1, $kunde->fresh()->notifications()->count());
    }

    public function test_verborgenes_projekt_benachrichtigt_den_kunden_nicht(): void
    {
        $offen = $this->stadium(['sortierung' => 1]);
        $wartend = $this->stadium(['sortierung' => 2, 'wartet_auf_kunde' => true]);

        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create(['kunden_sichtbar' => false]);

        $ticket = Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $offen->getKey(),
        ]);

        $ticket->update(['ticket_status_id' => $wartend->getKey()]);

        // Das Projekt ist aus seinem Bereich verschwunden — eine
        // Benachrichtigung mit dem Projektnamen im Text wäre trotzdem bei ihm
        // gelandet.
        $this->assertSame(0, $kunde->fresh()->notifications()->count());
    }

    public function test_interne_notiz_erreicht_den_kunden_nicht(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create();
        $ticket = Ticket::factory()->for($projekt, 'project')->create();

        $mitarbeiter = User::factory()->create(['rolle' => Rolle::Mitarbeiter]);

        Comment::factory()->for($ticket)->create([
            'user_id' => $mitarbeiter->getKey(),
            'ist_intern' => true,
        ]);

        $this->assertSame(0, $kunde->fresh()->notifications()->count());

        Comment::factory()->for($ticket)->create([
            'user_id' => $mitarbeiter->getKey(),
            'ist_intern' => false,
        ]);

        $this->assertSame(1, $kunde->fresh()->notifications()->count());
    }
}
