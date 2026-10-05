<?php

namespace Tests\Feature;

use App\Enums\Betreuung;
use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Customer;
use App\Models\Kontakt;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Support\Anfrage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Aus einer Anfrage der Website wird ein Kunde — ohne Abtippen, und ohne
 * dass der Link, den die Website gespeichert hat, danach ins Leere zeigt.
 */
class AnfrageWirdKundeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    /** @param array<string, mixed> $daten */
    private function anfrage(array $daten = []): Ticket
    {
        TicketStatus::factory()->create();
        $eingang = Customer::factory()->create(['name' => 'Eingang', 'kuerzel' => 'ANF']);
        $projekt = Project::factory()->for($eingang, 'customer')->create(['slug' => 'anfragen']);

        return Ticket::factory()->for($projekt, 'project')->create(array_merge([
            'titel' => 'Neue Website',
            'quelle' => Quelle::Website,
            'absender_name' => 'Erika Muster',
            'absender_email' => 'erika@example.de',
        ], $daten));
    }

    private function seite(Ticket $ticket, ?User $nutzer = null)
    {
        $this->actingAs($nutzer ?? $this->admin());
        Filament::setCurrentPanel('admin');

        return Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()]);
    }

    public function test_das_formular_ist_aus_dem_ticket_vorbelegt(): void
    {
        $this->seite($this->anfrage())
            ->mountAction('interessent')
            ->assertSchemaStateSet([
                'weg' => 'neu',
                'kontakt_name' => 'Erika Muster',
                'email' => 'erika@example.de',
                'kundenname' => 'Erika Muster',
                'kuerzel' => 'ERI',
            ]);
    }

    public function test_bestaetigen_legt_interessent_und_kontakt_an(): void
    {
        $ticket = $this->anfrage();

        // Nichts eingetippt: nur der Klick und die Bestätigung.
        $this->seite($ticket)
            ->callAction('interessent')
            ->assertHasNoActionErrors();

        $kunde = Customer::where('name', 'Erika Muster')->sole();
        $this->assertSame(Betreuung::Interessent, $kunde->betreuung);
        $this->assertSame('ERI', $kunde->kuerzel);

        $kontakt = $kunde->kontakte()->sole();
        $this->assertSame('Erika Muster', $kontakt->name);
        $this->assertSame('erika@example.de', $kontakt->email);
        $this->assertTrue($kontakt->hauptkontakt);

        $this->assertTrue($ticket->fresh()->interessent->is($kunde));
        $this->assertTrue($kunde->anfragen()->sole()->is($ticket));
    }

    public function test_der_link_aus_der_website_stimmt_danach_noch(): void
    {
        $ticket = $this->anfrage();
        $kennung = $ticket->kennung();
        $adresse = $ticket->oeffentlicheAdresse();

        $seite = $this->seite($ticket);
        $seite->callAction('interessent')->assertHasNoActionErrors();

        $danach = $ticket->fresh();
        $this->assertSame($kennung, $danach->kennung());
        $this->assertSame($adresse, $danach->oeffentlicheAdresse());
        $this->assertSame('ANF', $danach->customer->kuerzel);

        // Und die Adresse öffnet tatsächlich dieses Ticket.
        $this->get('/tickets/'.$ticket->getRouteKey())->assertOk();
    }

    public function test_der_neue_kunde_zaehlt_seine_tickets_von_vorn(): void
    {
        // Die Anfrage verbraucht keine Nummer beim neuen Kunden.
        $ticket = $this->anfrage();
        $this->seite($ticket)->callAction('interessent');

        $this->assertSame(0, $ticket->fresh()->interessent->ticket_zaehler);
    }

    public function test_ist_die_adresse_schon_bekannt_wird_die_zuordnung_angeboten(): void
    {
        $bestand = Customer::factory()->create(['name' => 'Muster GmbH']);
        Kontakt::create(['customer_id' => $bestand->getKey(), 'name' => 'E. Muster', 'email' => 'Erika@Example.de']);

        $ticket = $this->anfrage();
        $vorher = Customer::count();

        $this->seite($ticket)
            ->mountAction('interessent')
            // Vorbelegt ist die Zuordnung, nicht der neue Eintrag.
            ->assertSchemaStateSet(['weg' => 'bestehend'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame($vorher, Customer::count(), 'Es wurde ein zweiter Kunde angelegt.');
        $this->assertTrue($ticket->fresh()->interessent->is($bestand));
        // Der Kontakt war schon da und steht nicht doppelt.
        $this->assertSame(1, $bestand->kontakte()->count());
    }

    public function test_trotz_bekannter_adresse_laesst_sich_ein_neuer_kunde_anlegen(): void
    {
        $bestand = Customer::factory()->create();
        Kontakt::create(['customer_id' => $bestand->getKey(), 'name' => 'E. Muster', 'email' => 'erika@example.de']);

        $ticket = $this->anfrage();

        $this->seite($ticket)
            ->callAction('interessent', ['weg' => 'neu', 'kundenname' => 'Muster Neu', 'kuerzel' => 'MNE'])
            ->assertHasNoActionErrors();

        $this->assertSame('Muster Neu', $ticket->fresh()->interessent->name);
    }

    public function test_die_adresse_am_kunden_selbst_zaehlt_auch(): void
    {
        $bestand = Customer::factory()->create(['email' => 'erika@example.de']);

        $this->assertTrue(Anfrage::bekannterKunde($this->anfrage())->is($bestand));
    }

    public function test_ein_vergebenes_kuerzel_wird_nicht_vorgeschlagen(): void
    {
        Customer::factory()->create(['kuerzel' => 'ERI']);

        $this->assertSame('ER2', Anfrage::kuerzelVorschlag('Erika Muster'));
    }

    public function test_ein_vergebenes_kuerzel_wird_abgewiesen(): void
    {
        Customer::factory()->create(['kuerzel' => 'XYZ']);
        $ticket = $this->anfrage();

        $this->seite($ticket)
            ->callAction('interessent', ['kuerzel' => 'XYZ'])
            ->assertHasActionErrors(['kuerzel']);

        $this->assertNull($ticket->fresh()->interessent_id);
    }

    public function test_danach_ist_der_knopf_weg(): void
    {
        $ticket = $this->anfrage();
        $admin = $this->admin();

        $this->seite($ticket, $admin)->assertActionVisible('interessent');

        Anfrage::alsInteressent($ticket, ['kundenname' => 'Erika Muster', 'kuerzel' => 'ERI']);

        $this->seite($ticket->fresh(), $admin)->assertActionHidden('interessent');
    }

    public function test_ohne_absender_gibt_es_den_knopf_nicht(): void
    {
        $ticket = $this->anfrage(['absender_name' => null, 'absender_email' => null]);

        $this->seite($ticket)->assertActionHidden('interessent');
    }

    public function test_mitarbeiter_legen_keine_kunden_an(): void
    {
        // Kunden verwaltet nur der Admin (CustomerPolicy) — der Knopf am
        // Ticket darf kein Weg daran vorbei sein.
        $ticket = $this->anfrage();
        $mitarbeiter = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);
        $ticket->project->mitarbeiter()->attach($mitarbeiter);

        $this->seite($ticket, $mitarbeiter)->assertActionHidden('interessent');
    }
}
