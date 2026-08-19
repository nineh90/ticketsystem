<?php

namespace Tests\Feature;

use App\Enums\Prioritaet;
use App\Enums\Rolle;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kommentare, Verlauf und Zeiterfassung.
 */
class TicketArbeitTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
    }

    public function test_statuswechsel_landet_im_verlauf(): void
    {
        $nutzer = $this->admin();
        $this->actingAs($nutzer);

        $offen = TicketStatus::factory()->create();
        $arbeit = TicketStatus::factory()->create();

        $ticket = Ticket::factory()->create(['ticket_status_id' => $offen->id]);
        $ticket->update(['ticket_status_id' => $arbeit->id]);

        $eintrag = $ticket->activities()->latest('id')->first();

        $this->assertNotNull($eintrag);
        $this->assertSame('updated', $eintrag->event);
        $this->assertTrue($nutzer->is($eintrag->causer));

        // Die Feldänderungen stehen in v5 in attribute_changes, nicht in
        // properties — der Verlauf liest genau dort.
        $this->assertSame(
            $arbeit->id,
            $eintrag->attribute_changes['attributes']['ticket_status_id'],
        );
        $this->assertSame(
            $offen->id,
            $eintrag->attribute_changes['old']['ticket_status_id'],
        );
    }

    public function test_beschreibung_landet_nicht_im_verlauf(): void
    {
        // Sie wird beim Schreiben oft überarbeitet und würde den Verlauf mit
        // Textwänden zumüllen, in denen die interessanten Ereignisse
        // untergehen.
        $this->actingAs($this->admin());

        $ticket = Ticket::factory()->create();
        $vorher = $ticket->activities()->count();

        $ticket->update(['beschreibung' => 'Ein völlig neuer Text.']);

        $this->assertSame($vorher, $ticket->fresh()->activities()->count());
    }

    public function test_prioritaetswechsel_wird_protokolliert(): void
    {
        $this->actingAs($this->admin());

        $ticket = Ticket::factory()->create(['prioritaet' => Prioritaet::Normal]);
        $ticket->update(['prioritaet' => Prioritaet::Dringend]);

        $eintrag = $ticket->activities()->latest('id')->first();

        $this->assertSame('dringend', $eintrag->attribute_changes['attributes']['prioritaet']);
    }

    public function test_laufende_zeit_wird_erkannt_und_gestoppt(): void
    {
        $nutzer = $this->admin();
        $ticket = Ticket::factory()->create();

        $this->assertNull($nutzer->laufendeZeit());

        $eintrag = TimeEntry::create([
            'ticket_id' => $ticket->id,
            'user_id' => $nutzer->id,
            'gestartet_am' => now()->subMinutes(90),
        ]);

        $this->assertTrue($eintrag->laeuft());
        $this->assertTrue($eintrag->is($nutzer->fresh()->laufendeZeit()));

        $eintrag->stoppen();

        $this->assertNull($nutzer->fresh()->laufendeZeit());
        $this->assertSame(90, $eintrag->fresh()->minuten);
    }

    public function test_stoppen_einer_bereits_beendeten_buchung_aendert_nichts(): void
    {
        $eintrag = TimeEntry::factory()->create(['minuten' => 42]);

        $eintrag->stoppen();

        $this->assertSame(42, $eintrag->fresh()->minuten);
    }

    public function test_erfasste_minuten_summieren_sich_am_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        TimeEntry::factory()->for($ticket, 'ticket')->create(['minuten' => 30]);
        TimeEntry::factory()->for($ticket, 'ticket')->create(['minuten' => 45]);
        // Eine andere Buchung an einem anderen Ticket darf nicht mitzählen.
        TimeEntry::factory()->create(['minuten' => 999]);

        $this->assertSame(75, $ticket->erfassteMinuten());
    }

    public function test_projektstunden_rechnen_ueber_alle_tickets(): void
    {
        $ticket = Ticket::factory()->create();
        $projekt = $ticket->project;

        TimeEntry::factory()->for($ticket, 'ticket')->create(['minuten' => 90]);
        TimeEntry::factory()
            ->for(Ticket::factory()->for($projekt, 'project'), 'ticket')
            ->create(['minuten' => 30]);

        $this->assertSame(2.0, $projekt->erfassteStunden());
    }

    public function test_geloeschtes_ticket_nimmt_kommentare_und_zeiten_mit(): void
    {
        $ticket = Ticket::factory()->create();
        Comment::factory()->for($ticket, 'ticket')->create();
        TimeEntry::factory()->for($ticket, 'ticket')->create();

        $id = $ticket->id;
        $ticket->delete();

        $this->assertSame(0, Comment::where('ticket_id', $id)->count());
        $this->assertSame(0, TimeEntry::where('ticket_id', $id)->count());
    }
}
