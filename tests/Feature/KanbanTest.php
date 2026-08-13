<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Pages\Kanban;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KanbanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
    }

    public function test_brett_laedt(): void
    {
        $this->actingAs($this->admin())->get('/kanban')->assertOk();
    }

    public function test_karte_wechselt_die_spalte(): void
    {
        $admin = $this->admin();

        $offen = TicketStatus::factory()->create(['sortierung' => 1]);
        $arbeit = TicketStatus::factory()->create(['sortierung' => 2]);

        $ticket = Ticket::factory()->create(['ticket_status_id' => $offen->id]);

        Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->call('verschieben', $ticket->id, $arbeit->id, [$ticket->id])
            ->assertHasNoErrors();

        $this->assertSame($arbeit->id, $ticket->fresh()->ticket_status_id);
    }

    public function test_ablegen_in_abschliessendes_stadium_setzt_erledigt(): void
    {
        $admin = $this->admin();

        $offen = TicketStatus::factory()->create();
        $fertig = TicketStatus::factory()->abschluss()->create();

        $ticket = Ticket::factory()->create(['ticket_status_id' => $offen->id]);

        Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->call('verschieben', $ticket->id, $fertig->id, [$ticket->id]);

        $this->assertNotNull($ticket->fresh()->erledigt_at);
    }

    public function test_reihenfolge_wird_festgeschrieben(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();

        $a = Ticket::factory()->create(['ticket_status_id' => $status->id]);
        $b = Ticket::factory()->create(['ticket_status_id' => $status->id]);
        $c = Ticket::factory()->create(['ticket_status_id' => $status->id]);

        Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->call('verschieben', $c->id, $status->id, [$c->id, $a->id, $b->id]);

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_fremdes_ticket_laesst_sich_nicht_verschieben(): void
    {
        // Ein Mitarbeiter könnte sonst per Livewire-Aufruf ein Ticket aus
        // einem Projekt bewegen, das er gar nicht sehen darf — das Brett
        // zeigt es ihm zwar nicht, aber der Aufruf ginge trotzdem durch.
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $offen = TicketStatus::factory()->create();
        $arbeit = TicketStatus::factory()->create();

        $fremdes = Ticket::factory()->create(['ticket_status_id' => $offen->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($mitarbeiter)
            ->test(Kanban::class)
            ->call('verschieben', $fremdes->id, $arbeit->id, [$fremdes->id]);
    }

    public function test_mitarbeiter_verschiebt_im_eigenen_projekt(): void
    {
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $projekt = Project::factory()->create();
        $projekt->mitarbeiter()->attach($mitarbeiter);

        $offen = TicketStatus::factory()->create();
        $arbeit = TicketStatus::factory()->create();

        $ticket = Ticket::factory()->for($projekt, 'project')
            ->create(['ticket_status_id' => $offen->id]);

        Livewire::actingAs($mitarbeiter)
            ->test(Kanban::class)
            ->call('verschieben', $ticket->id, $arbeit->id, [$ticket->id])
            ->assertHasNoErrors();

        $this->assertSame($arbeit->id, $ticket->fresh()->ticket_status_id);
    }

    public function test_projektfilter_beschraenkt_das_brett(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();

        $projekt = Project::factory()->create();
        $gewollt = Ticket::factory()->for($projekt, 'project')
            ->create(['ticket_status_id' => $status->id, 'titel' => 'Gehoert dazu']);
        Ticket::factory()->create(['ticket_status_id' => $status->id, 'titel' => 'Anderes Projekt']);

        $this->actingAs($admin)
            ->get('/kanban?projekt='.$projekt->id)
            ->assertOk()
            ->assertSee('Gehoert dazu')
            ->assertDontSee('Anderes Projekt');
    }
}
