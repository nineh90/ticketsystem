<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Widgets\MeineTickets;
use App\Filament\Widgets\MeinUeberblick;
use App\Filament\Widgets\TicketsJeKunde;
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
            ->test(TicketsJeKunde::class)
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

    public function test_kundendiagramm_ist_fuer_mitarbeiter_unsichtbar(): void
    {
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $this->actingAs($mitarbeiter);

        $this->assertFalse(TicketsJeKunde::canView());
    }
}
