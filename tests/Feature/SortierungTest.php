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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Oben steht, was am längsten wartet (NID-37).
 *
 * Vorher standen neue Tickets oben — im Kanban wie in der Liste. Weil man
 * von oben nach unten abarbeitet, kam das Neueste zuerst dran, und die alten
 * lagen immer länger. Unter "Erledigt" wird nicht abgearbeitet, sondern
 * gesucht; dort gilt die Ticketnummer.
 */
class SortierungTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TicketStatus $offen;

    private Project $projekt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $this->offen = TicketStatus::factory()->create(['sortierung' => 1]);
        $this->projekt = Project::factory()->for(Customer::factory()->create(), 'customer')->create();
    }

    private function ticket(string $titel, ?TicketStatus $stadium = null, int $vorTagen = 0): Ticket
    {
        $this->travelTo(now()->subDays($vorTagen));

        $ticket = Ticket::factory()->for($this->projekt, 'project')->create([
            'titel' => $titel,
            'ticket_status_id' => ($stadium ?? $this->offen)->id,
        ]);

        $this->travelBack();

        return $ticket;
    }

    /** @return array<int, string> */
    private function spalte(TicketStatus $stadium): array
    {
        $spalten = Livewire::actingAs($this->admin)->test(Kanban::class)->instance()->getSpalten();

        return $spalten->firstWhere('stadium.id', $stadium->id)->karten->pluck('titel')->all();
    }

    public function test_ein_neues_ticket_stellt_sich_hinten_an(): void
    {
        $this->ticket('Erstes');
        $this->ticket('Zweites');
        $this->ticket('Drittes');

        $this->assertSame(['Erstes', 'Zweites', 'Drittes'], $this->spalte($this->offen));
    }

    public function test_wer_die_spalte_wechselt_kommt_ans_ende(): void
    {
        $review = TicketStatus::factory()->create(['sortierung' => 2]);

        $this->ticket('Liegt schon in Review', $review);
        $neu = $this->ticket('Gerade fertig geworden');

        $neu->update(['ticket_status_id' => $review->id]);

        $this->assertSame(['Liegt schon in Review', 'Gerade fertig geworden'], $this->spalte($review));
    }

    public function test_von_hand_gezogen_bleibt_von_hand_gezogen(): void
    {
        $a = $this->ticket('A');
        $b = $this->ticket('B');

        Livewire::actingAs($this->admin)
            ->test(Kanban::class)
            ->call('verschieben', $b->id, $this->offen->id, [$b->id, $a->id]);

        $this->assertSame(['B', 'A'], $this->spalte($this->offen));
    }

    public function test_erledigte_stehen_nach_nummer_die_hoechste_oben(): void
    {
        $erledigt = TicketStatus::factory()->abschluss()->create(['sortierung' => 9]);

        $eins = $this->ticket('Nummer eins');
        $zwei = $this->ticket('Nummer zwei');
        $drei = $this->ticket('Nummer drei');

        // In anderer Reihenfolge erledigt, als sie angelegt wurden.
        foreach ([$zwei, $drei, $eins] as $ticket) {
            $ticket->update(['ticket_status_id' => $erledigt->id]);
        }

        $this->assertSame(['Nummer drei', 'Nummer zwei', 'Nummer eins'], $this->spalte($erledigt));
    }

    public function test_die_liste_zeigt_das_aelteste_zuerst_und_erledigte_nach_nummer(): void
    {
        $alt = $this->ticket('Alt', vorTagen: 10);
        $mittel = $this->ticket('Mittel', vorTagen: 5);
        $neu = $this->ticket('Neu');

        Livewire::actingAs($this->admin)
            ->test(ListTickets::class)
            ->set('activeTab', 'offen')
            ->assertCanSeeTableRecords([$alt, $mittel, $neu], inOrder: true);

        $erledigt = TicketStatus::factory()->abschluss()->create();
        foreach ([$neu, $alt, $mittel] as $ticket) {
            $ticket->update(['ticket_status_id' => $erledigt->id]);
        }

        Livewire::actingAs($this->admin)
            ->test(ListTickets::class)
            ->set('activeTab', 'erledigt')
            ->assertCanSeeTableRecords([$neu, $mittel, $alt], inOrder: true);
    }
}
