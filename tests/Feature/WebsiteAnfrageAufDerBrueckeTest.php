<?php

namespace Tests\Feature;

use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Widgets\VonKunden;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Eine Anfrage über die Website steht auf der Brücke dort, wo auch die
 * Meldungen der Kunden stehen (NID-24).
 *
 * Für uns ist es dieselbe Lage: draußen wartet jemand. Wer noch kein Kunde
 * ist, wartet eher ungeduldiger.
 */
class WebsiteAnfrageAufDerBrueckeTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-fuer-die-website';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ticketsystem.api_token_website' => self::TOKEN]);

        // Reste des Nebenläufigkeitstests, siehe UebersichtTest.
        Ticket::query()->delete();

        TicketStatus::factory()->create(['sortierung' => 1]);
        $eingang = Customer::factory()->create(['name' => 'Eingang', 'kuerzel' => 'ANF']);
        Project::factory()->for($eingang, 'customer')->create(['slug' => 'anfragen', 'name' => 'Anfragen']);

        $this->admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    private function anfrage(): Ticket
    {
        // Über die Schnittstelle, wie die Website es tut — nicht über die
        // Fabrik: geprüft wird der ganze Weg bis zur Glocke.
        $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->postJson('/api/v1/tickets', [
                'projekt' => 'anfragen',
                'titel' => 'Neue Website für den Verein',
                'absender_name' => 'Erika Muster',
                'absender_email' => 'erika@example.de',
                'external_ref' => 'website-anfrage-1',
            ])
            ->assertStatus(201);

        return Ticket::sole();
    }

    public function test_die_glocke_meldet_die_anfrage_mit_dem_absender(): void
    {
        $this->anfrage();

        $meldung = $this->admin->notifications()->sole();

        $this->assertSame('Anfrage über die Website von Erika Muster', $meldung->data['title']);
        $this->assertStringContainsString('ANF-1', $meldung->data['body']);
    }

    public function test_die_wiederholung_meldet_nicht_noch_einmal(): void
    {
        // Die Website wiederholt bei Fehlern bis zu dreimal.
        $this->anfrage();

        $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->postJson('/api/v1/tickets', [
                'projekt' => 'anfragen',
                'titel' => 'Neue Website für den Verein',
                'external_ref' => 'website-anfrage-1',
            ])
            ->assertStatus(200);

        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_was_n8n_einliefert_meldet_weiter_nichts(): void
    {
        config(['ticketsystem.api_token' => 'n8n-token']);

        $this->withHeaders(['Authorization' => 'Bearer n8n-token'])
            ->postJson('/api/v1/tickets', ['projekt' => 'anfragen', 'titel' => 'Aus einer Mail'])
            ->assertStatus(201);

        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_die_anfrage_steht_in_der_karte_auf_dem_dashboard(): void
    {
        $ticket = $this->anfrage();

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        $this->assertTrue(VonKunden::canView());

        Livewire::test(VonKunden::class)
            ->assertCanSeeTableRecords([$ticket])
            ->assertSee('Über die Website')
            ->assertSee('Erika Muster');
    }

    public function test_die_anfrage_steht_im_reiter_der_ticketliste(): void
    {
        $ticket = $this->anfrage();
        $eigenes = Ticket::factory()->create(['quelle' => Quelle::Manuell]);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'von-kunden')
            ->assertCanSeeTableRecords([$ticket])
            ->assertCanNotSeeTableRecords([$eigenes]);
    }

    public function test_am_ticket_steht_dass_per_mail_geantwortet_wird(): void
    {
        $ticket = $this->anfrage();

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()])
            ->assertSee('Über die Website angefragt')
            ->assertSee('erika@example.de');
    }
}
