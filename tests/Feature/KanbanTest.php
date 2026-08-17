<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Pages\Kanban;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
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

    public function test_ein_projekt_aus_der_adresse_zieht_seinen_kunden_mit(): void
    {
        // Sonst stünde im Kundenfeld "Alle Kunden" und im Projektfeld ein
        // einzelnes Projekt — zwei Felder, die sich widersprechen.
        $admin = $this->admin();
        $projekt = Project::factory()->create();

        Livewire::actingAs($admin)
            ->withQueryParams(['projekt' => $projekt->id])
            ->test(Kanban::class)
            ->assertSet('projektId', $projekt->id)
            ->assertSet('kundeId', $projekt->customer_id);
    }

    public function test_die_vorauswahl_steht_beim_naechsten_aufruf_wieder_da(): void
    {
        // Der eigentliche Wunsch: einmal einstellen, dann Dashboard, dann
        // zurück — und es steht noch so da.
        $admin = $this->admin();
        $projekt = Project::factory()->create();

        Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->set('kundeId', $projekt->customer_id)
            ->set('projektId', $projekt->id)
            ->set('nurMeine', true);

        Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->assertSet('kundeId', $projekt->customer_id)
            ->assertSet('projektId', $projekt->id)
            ->assertSet('nurMeine', true);
    }

    public function test_kundenwechsel_wirft_das_fremde_projekt_hinaus(): void
    {
        $admin = $this->admin();
        $einer = Project::factory()->create();
        $anderer = Customer::factory()->create();

        Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->set('projektId', $einer->id)
            ->set('kundeId', $anderer->id)
            ->assertSet('projektId', null);
    }

    public function test_eine_geloeschte_vorauswahl_laesst_das_brett_nicht_leer_stehen(): void
    {
        // Ein gemerktes Projekt kann verschwinden. Bliebe die Vorauswahl
        // stehen, zeigte das Brett nichts und das Auswahlfeld dazu keinen
        // Grund — es sähe aus, als gäbe es keine Tickets mehr.
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();
        Ticket::factory()->create(['ticket_status_id' => $status->id, 'titel' => 'Lebt noch']);

        $projekt = Project::factory()->create();

        Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->set('projektId', $projekt->id);

        $projekt->delete();

        Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->assertSet('projektId', null)
            ->assertSee('Lebt noch');
    }

    public function test_nur_meine_zeigt_fremde_karten_nicht(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();

        Ticket::factory()->create([
            'ticket_status_id' => $status->id,
            'assigned_to' => $admin->id,
            'titel' => 'Meine Karte',
        ]);
        Ticket::factory()->create([
            'ticket_status_id' => $status->id,
            'titel' => 'Fremde Karte',
        ]);

        Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->assertSee('Fremde Karte')
            ->set('nurMeine', true)
            ->assertSee('Meine Karte')
            ->assertDontSee('Fremde Karte');
    }

    public function test_abschlussspalte_wird_gekappt_aber_zaehlt_vollstaendig(): void
    {
        // Erledigtes sammelt sich für immer an. Gekappt wird die Anzeige,
        // nicht die Zahl darüber — sonst behauptete das Brett, es gäbe genau
        // fünfzehn erledigte Tickets.
        $admin = $this->admin();
        $fertig = TicketStatus::factory()->abschluss()->create(['name' => 'Erledigt']);

        Ticket::factory()->count(20)->create(['ticket_status_id' => $fertig->id]);

        $spalte = Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->instance()
            ->getSpalten()
            ->firstWhere(fn ($s) => $s->stadium->is($fertig));

        $this->assertSame(20, $spalte->gesamt);
        $this->assertSame(15, $spalte->karten->count());
        $this->assertSame(5, $spalte->verborgen);
    }

    public function test_offene_spalten_bleiben_vollstaendig(): void
    {
        // Die Grenze gilt ausdrücklich nur für Abgeschlossenes. Jede offene
        // Karte ist etwas, das noch jemand anfassen muss — die darf nicht
        // hinter einem "und 12 weitere" verschwinden.
        $admin = $this->admin();
        $offen = TicketStatus::factory()->create(['ist_abschluss' => false]);

        Ticket::factory()->count(22)->create(['ticket_status_id' => $offen->id]);

        $spalte = Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->instance()
            ->getSpalten()
            ->firstWhere(fn ($s) => $s->stadium->is($offen));

        $this->assertSame(22, $spalte->gesamt);
        $this->assertSame(22, $spalte->karten->count());
        $this->assertSame(0, $spalte->verborgen);
    }

    public function test_der_weg_zu_den_gekappten_karten_zeigt_dieselbe_menge(): void
    {
        // "… und 5 weitere" ist ein Versprechen wie eine Dashboard-Kachel:
        // dahinter müssen genau die Tickets dieser Spalte stehen.
        $admin = $this->admin();
        $fertig = TicketStatus::factory()->abschluss()->create();
        $projekt = Project::factory()->create();

        Ticket::factory()->count(18)->for($projekt, 'project')
            ->create(['ticket_status_id' => $fertig->id]);
        // Andere Spalte und anderes Projekt — beides darf nicht mitkommen.
        Ticket::factory()->count(3)->create([
            'ticket_status_id' => TicketStatus::factory()->create()->id,
        ]);
        Ticket::factory()->count(4)->create(['ticket_status_id' => $fertig->id]);

        $brett = Livewire::actingAs($admin)
            ->test(Kanban::class)
            ->set('projektId', $projekt->id)
            ->instance();

        parse_str((string) parse_url($brett->listeFuerStadium($fertig), PHP_URL_QUERY), $parameter);

        $anzahl = Livewire::withQueryParams($parameter)
            ->actingAs($admin)
            ->test(ListTickets::class)
            ->instance()
            ->getFilteredTableQuery()
            ->count();

        $this->assertSame(18, $anzahl);
    }
}
