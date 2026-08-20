<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Wer die Uhr startet, arbeitet daran.
 *
 * Der Handgriff, um den es geht, macht man den ganzen Tag: Uhr starten, dann
 * die Karte auf dem Deck eine Spalte weiterziehen. Beides sagt dasselbe, und
 * wer das Zweite vergisst, hat ein Brett, das nicht mehr stimmt.
 *
 * Getestet wird deshalb vor allem die Grenze — was den Stand NICHT umschreiben
 * darf. Eine Automatik, die zu viel tut, ist schlimmer als gar keine: sie
 * ändert Dinge, die niemand angefasst hat, und man findet den Grund nicht.
 */
class UhrVerschiebtTicketTest extends TestCase
{
    use RefreshDatabase;

    private function stadium(string $slug, string $name, array $daten = []): TicketStatus
    {
        return TicketStatus::factory()->create(array_merge([
            'slug' => $slug,
            'name' => $name,
        ], $daten));
    }

    private function ticket(TicketStatus $stadium, ?User $fuer = null): Ticket
    {
        $kunde = Customer::factory()->create();

        if ($fuer) {
            $kunde->mitarbeiter()->attach($fuer);
        }

        return Ticket::factory()
            ->for($stadium, 'status')
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create(['customer_id' => $kunde->getKey()]);
    }

    private function uhrStarten(Ticket $ticket, User $wer): TimeEntry
    {
        return TimeEntry::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $wer->getKey(),
            'gestartet_am' => now(),
        ]);
    }

    private function mitarbeiter(): User
    {
        return User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);
    }

    public function test_die_laufende_uhr_schiebt_das_ticket_auf_in_arbeit(): void
    {
        $offen = $this->stadium('offen', 'Offen');
        $inArbeit = $this->stadium(TicketStatus::IN_ARBEIT, 'In Arbeit');

        $ticket = $this->ticket($offen);

        $this->uhrStarten($ticket, $this->mitarbeiter());

        $this->assertTrue($ticket->fresh()->status->is($inArbeit));
    }

    public function test_ein_nachtrag_laesst_den_stand_in_ruhe(): void
    {
        // "Gestern zwei Stunden" beschreibt Vergangenes. Sonst zöge das
        // Aufräumen der letzten Woche erledigte Tickets zurück aufs Brett.
        $erledigt = $this->stadium('erledigt', 'Erledigt', ['ist_abschluss' => true]);
        $this->stadium(TicketStatus::IN_ARBEIT, 'In Arbeit');

        $ticket = $this->ticket($erledigt);

        TimeEntry::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $this->mitarbeiter()->getKey(),
            'gestartet_am' => now()->subDay(),
            'beendet_am' => now()->subDay()->addHours(2),
            'minuten' => 120,
        ]);

        $this->assertTrue($ticket->fresh()->status->is($erledigt));
    }

    public function test_ein_erledigtes_ticket_kommt_zurueck_aufs_brett(): void
    {
        // Bewusst so: wer an einem abgeschlossenen Ticket die Uhr startet,
        // arbeitet daran. Dann ist es nicht mehr erledigt — und erledigt_at
        // fällt mit (Ticket::booted).
        $erledigt = $this->stadium('erledigt', 'Erledigt', ['ist_abschluss' => true]);
        $inArbeit = $this->stadium(TicketStatus::IN_ARBEIT, 'In Arbeit');

        $ticket = $this->ticket($erledigt);
        $ticket->refresh();
        $this->assertNotNull($ticket->erledigt_at);

        $this->uhrStarten($ticket, $this->mitarbeiter());

        $frisch = $ticket->fresh();
        $this->assertTrue($frisch->status->is($inArbeit));
        $this->assertNull($frisch->erledigt_at);
    }

    public function test_ohne_das_stadium_passiert_nichts(): void
    {
        // Wer "In Arbeit" gelöscht hat, arbeitet mit anderen Spalten. Das ist
        // kein Fehlerfall, sondern eine Entscheidung.
        $offen = $this->stadium('offen', 'Offen');

        $ticket = $this->ticket($offen);

        $this->uhrStarten($ticket, $this->mitarbeiter());

        $this->assertTrue($ticket->fresh()->status->is($offen));
    }

    public function test_der_wechsel_steht_im_verlauf(): void
    {
        $offen = $this->stadium('offen', 'Offen');
        $this->stadium(TicketStatus::IN_ARBEIT, 'In Arbeit');

        $ticket = $this->ticket($offen);

        $this->uhrStarten($ticket, $this->mitarbeiter());

        $this->assertSame(
            1,
            $ticket->activities()->where('description', 'updated')->count(),
            'Der Stadienwechsel muss im Verlauf stehen wie jeder andere auch.',
        );
    }

    public function test_der_startknopf_sagt_dass_er_das_ticket_verschoben_hat(): void
    {
        $offen = $this->stadium('offen', 'Offen');
        $this->stadium(TicketStatus::IN_ARBEIT, 'In Arbeit');

        $nutzer = $this->mitarbeiter();
        $ticket = $this->ticket($offen, $nutzer);

        Livewire::actingAs($nutzer)
            ->test(TimeEntriesRelationManager::class, [
                'ownerRecord' => $ticket,
                'pageClass' => ViewTicket::class,
            ])
            ->callAction(TestAction::make('starten')->table())
            ->assertHasNoActionErrors();

        $this->assertTrue($ticket->fresh()->status->is(TicketStatus::inArbeit()));
        $this->assertNotNull(TimeEntry::query()->where('ticket_id', $ticket->getKey())->first());
    }
}
