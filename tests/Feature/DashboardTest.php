<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Pages\Betrieb;
use App\Filament\Pages\MeinBereich;
use App\Filament\Widgets\MeineTickets;
use App\Filament\Widgets\MeinUeberblick;
use App\Filament\Widgets\TeamUeberblick;
use App\Filament\Widgets\TicketsVerteilung;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die Dashboard-Widgets.
 *
 * Diese Tests gäbe es ohne einen konkreten Anlass nicht: das Kunden-Diagramm
 * ist beim ersten Aufruf mit einem SQL-Fehler ausgestiegen, weil Postgres in
 * HAVING keinen Unterabfrage-Alias zulässt — unter MySQL wäre es
 * durchgegangen. Widgets werden von keinem anderen Test berührt, also braucht
 * es eigene.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
    }

    public function test_dashboard_laedt_mit_daten(): void
    {
        $admin = $this->admin();

        $status = TicketStatus::factory()->create();
        Ticket::factory()->count(3)->create([
            'ticket_status_id' => $status->id,
            'assigned_to' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/')->assertOk();
    }

    public function test_betrieb_liegt_auf_einer_eigenen_seite(): void
    {
        $admin = $this->admin();

        $status = TicketStatus::factory()->create();
        Ticket::factory()->create([
            'ticket_status_id' => $status->id,
            'assigned_to' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/betrieb')->assertOk();
    }

    public function test_die_beiden_seiten_teilen_sich_die_karten_ohne_ueberschneidung(): void
    {
        // Der eigentliche Zweck der Trennung. Ein Widget auf beiden Seiten
        // wäre kein Fehler, den man sieht — es sähe nur wieder nach der alten
        // Wand aus Karten aus, und zwar zweimal.
        $meins = (new MeinBereich)->getWidgets();
        $betrieb = (new Betrieb)->getWidgets();

        $this->assertSame([], array_intersect($meins, $betrieb));

        $this->assertContains(MeinUeberblick::class, $meins);
        $this->assertContains(MeineTickets::class, $meins);
        $this->assertNotContains(TeamUeberblick::class, $meins);

        $this->assertContains(TeamUeberblick::class, $betrieb);
        $this->assertContains(TicketsVerteilung::class, $betrieb);
        $this->assertNotContains(MeineTickets::class, $betrieb);
    }

    public function test_kundendiagramm_laeuft_unter_postgres(): void
    {
        $admin = $this->admin();

        $status = TicketStatus::factory()->create();
        $mitTickets = Customer::factory()->create();
        // Ein Kunde ganz ohne Tickets muss aus dem Diagramm fallen, ohne dass
        // die Abfrage dafür ein HAVING braucht.
        Customer::factory()->create();

        Ticket::factory()->count(2)
            ->for(Project::factory()->for($mitTickets, 'customer'), 'project')
            ->create(['ticket_status_id' => $status->id]);

        Livewire::actingAs($admin)
            ->test(TicketsVerteilung::class)
            ->assertSuccessful();
    }

    public function test_ueberblick_zaehlt_nur_eigene_tickets(): void
    {
        $admin = $this->admin();
        $anderer = $this->admin();

        $status = TicketStatus::factory()->create();

        Ticket::factory()->count(2)->create([
            'ticket_status_id' => $status->id,
            'assigned_to' => $admin->id,
        ]);
        Ticket::factory()->create([
            'ticket_status_id' => $status->id,
            'assigned_to' => $anderer->id,
        ]);

        Livewire::actingAs($admin)
            ->test(MeinUeberblick::class)
            ->assertSuccessful()
            ->assertSee('Meine offenen Tickets');
    }

    public function test_meine_tickets_zeigt_fremde_nicht(): void
    {
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $status = TicketStatus::factory()->create();

        $projekt = Project::factory()->create();
        $projekt->mitarbeiter()->attach($mitarbeiter);

        $meins = Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $status->id,
            'assigned_to' => $mitarbeiter->id,
            'titel' => 'Mein eigenes Ticket',
        ]);
        // Fremdes Projekt, aber ihm zugewiesen — darf trotzdem nicht
        // erscheinen, sonst umgeht das Dashboard die Projekttrennung.
        $fremd = Ticket::factory()->create([
            'ticket_status_id' => $status->id,
            'assigned_to' => $mitarbeiter->id,
            'titel' => 'Fremdes Ticket',
        ]);

        Livewire::actingAs($mitarbeiter)
            ->test(MeineTickets::class)
            ->assertCanSeeTableRecords([$meins])
            ->assertCanNotSeeTableRecords([$fremd]);
    }

    public function test_diagramm_bleibt_ohne_zuordnung_weg(): void
    {
        // Ohne Kunde und ohne Projekt gäbe es nichts zu verteilen; das
        // Diagramm zeigte dann eine leere Achse ohne erkennbaren Grund.
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $this->actingAs($mitarbeiter);

        $this->assertFalse(TicketsVerteilung::canView());
    }

    public function test_mitarbeiter_sieht_die_verteilung_seiner_projekte(): void
    {
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $status = TicketStatus::factory()->create(['ist_abschluss' => false]);

        $meins = Project::factory()->create(['name' => 'Mein Projekt']);
        $meins->mitarbeiter()->attach($mitarbeiter);
        Ticket::factory()->count(2)->for($meins, 'project')->create([
            'ticket_status_id' => $status->id,
        ]);

        // Fremdes Projekt mit mehr Tickets — es darf im Diagramm nicht
        // auftauchen, sonst verriete die Verteilung fremde Auslastung.
        $fremd = Project::factory()->create(['name' => 'Fremdes Projekt']);
        Ticket::factory()->count(4)->for($fremd, 'project')->create([
            'ticket_status_id' => $status->id,
        ]);

        $this->actingAs($mitarbeiter);
        $this->assertTrue(TicketsVerteilung::canView());

        Livewire::actingAs($mitarbeiter)
            ->test(TicketsVerteilung::class)
            ->assertSuccessful()
            ->assertSee('Offene Tickets je Projekt')
            ->assertSee('Mein Projekt')
            ->assertDontSee('Fremdes Projekt');
    }
}
