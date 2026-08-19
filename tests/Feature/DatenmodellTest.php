<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatenmodellTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticketnummern_laufen_je_kunde_fortlaufend(): void
    {
        $a = Customer::factory()->create(['kuerzel' => 'AAA']);
        $b = Customer::factory()->create(['kuerzel' => 'BBB']);

        $projektA = Project::factory()->for($a, 'customer')->create();
        $projektB = Project::factory()->for($b, 'customer')->create();

        $a1 = Ticket::factory()->for($projektA, 'project')->create();
        $a2 = Ticket::factory()->for($projektA, 'project')->create();
        $b1 = Ticket::factory()->for($projektB, 'project')->create();

        $this->assertSame(1, $a1->nummer);
        $this->assertSame(2, $a2->nummer);

        // Der zweite Kunde fängt wieder bei 1 an — die Nummer ist kundenweit,
        // nicht global.
        $this->assertSame(1, $b1->nummer);

        $this->assertSame('AAA-2', $a2->kennung());
        $this->assertSame('BBB-1', $b1->kennung());
    }

    public function test_zwei_projekte_desselben_kunden_teilen_den_nummernkreis(): void
    {
        // Sonst hätten beide Projekte ein "LDX-1" und die Kennung wäre
        // mehrdeutig.
        $kunde = Customer::factory()->create(['kuerzel' => 'LDX']);

        $eins = Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')->create();
        $zwei = Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')->create();

        $this->assertSame(1, $eins->nummer);
        $this->assertSame(2, $zwei->nummer);
    }

    public function test_customer_id_wird_aus_dem_projekt_abgeleitet(): void
    {
        $kunde = Customer::factory()->create();
        $projekt = Project::factory()->for($kunde, 'customer')->create();

        $ticket = Ticket::factory()->for($projekt, 'project')->create();

        $this->assertSame($kunde->id, $ticket->customer_id);
    }

    public function test_doppelte_nummer_wird_von_der_datenbank_abgewiesen(): void
    {
        // Das Netz unter der Sperre: selbst wenn die Vergabe umgangen wird,
        // darf keine Nummer zweimal existieren.
        $kunde = Customer::factory()->create();
        $projekt = Project::factory()->for($kunde, 'customer')->create();

        Ticket::factory()->for($projekt, 'project')->create(['nummer' => 5]);

        $this->expectException(QueryException::class);

        Ticket::factory()->for($projekt, 'project')->create(['nummer' => 5]);
    }

    public function test_external_ref_ist_eindeutig(): void
    {
        // Grundlage der Idempotenz für n8n: ein Wiederholungslauf darf kein
        // zweites Ticket erzeugen.
        Ticket::factory()->create(['external_ref' => 'mail-123']);

        $this->expectException(QueryException::class);

        Ticket::factory()->create(['external_ref' => 'mail-123']);
    }

    public function test_abschliessendes_stadium_setzt_erledigt_at(): void
    {
        $offen = TicketStatus::factory()->create(['sortierung' => 1]);
        $fertig = TicketStatus::factory()->abschluss()->create(['sortierung' => 2]);

        $ticket = Ticket::factory()->create(['ticket_status_id' => $offen->id]);
        $this->assertNull($ticket->erledigt_at);

        $ticket->update(['ticket_status_id' => $fertig->id]);
        $this->assertNotNull($ticket->fresh()->erledigt_at);

        // Und zurück: ein wiedereröffnetes Ticket gilt nicht mehr als erledigt.
        $ticket->update(['ticket_status_id' => $offen->id]);
        $this->assertNull($ticket->fresh()->erledigt_at);
    }

    public function test_kuerzel_wird_immer_gross_geschrieben(): void
    {
        $kunde = Customer::factory()->create(['kuerzel' => 'ldx']);

        $this->assertSame('LDX', $kunde->fresh()->kuerzel);
    }

    public function test_kommentare_sind_im_zweifel_intern(): void
    {
        // Die Richtung dieses Defaults ist die ganze Absicherung für den
        // späteren Kundenbereich.
        $kommentar = Comment::create([
            'ticket_id' => Ticket::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'Notiz',
        ]);

        $this->assertTrue($kommentar->ist_intern);
    }

    public function test_zeiterfassung_rechnet_ueber_mitternacht(): void
    {
        $eintrag = TimeEntry::factory()->create([
            'gestartet_am' => '2026-08-13 23:30:00',
            'beendet_am' => null,
            'minuten' => 0,
        ]);

        $eintrag->stoppen(new \DateTimeImmutable('2026-08-14 00:15:00'));

        $this->assertSame(45, $eintrag->fresh()->minuten);
        $this->assertFalse($eintrag->fresh()->laeuft());
    }

    public function test_mitarbeiter_sieht_nur_zugeordnete_projekte(): void
    {
        $mitarbeiter = User::factory()->create(['panel_zugang' => true]);

        $meins = Project::factory()->create();
        $fremdes = Project::factory()->create();
        $meins->mitarbeiter()->attach($mitarbeiter);

        Ticket::factory()->for($meins, 'project')->create();
        Ticket::factory()->for($fremdes, 'project')->create();

        $sichtbareProjekte = Project::query()->sichtbarFuer($mitarbeiter)->pluck('id');
        $this->assertEqualsCanonicalizing([$meins->id], $sichtbareProjekte->all());

        // Entscheidend: das fremde Ticket taucht schon in der Abfrage nicht
        // auf, wird also auch nicht mitgezählt.
        $this->assertSame(1, Ticket::query()->sichtbarFuer($mitarbeiter)->count());
    }

    public function test_admin_sieht_alle_projekte(): void
    {
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        Project::factory()->count(3)->create();

        $this->assertSame(3, Project::query()->sichtbarFuer($admin)->count());
    }

    public function test_offene_tickets_schliessen_erledigte_aus(): void
    {
        $offen = TicketStatus::factory()->create();
        $fertig = TicketStatus::factory()->abschluss()->create();

        Ticket::factory()->count(2)->create(['ticket_status_id' => $offen->id]);
        Ticket::factory()->create(['ticket_status_id' => $fertig->id]);

        $this->assertSame(2, Ticket::query()->offen()->count());
    }

    public function test_kunde_mit_projekten_laesst_sich_nicht_loeschen(): void
    {
        // Inaktiv setzen ist der vorgesehene Weg; ein Löschversuch ist fast
        // immer ein Versehen und würde Tickets und Zeiten mitnehmen.
        $kunde = Customer::factory()->create();
        Project::factory()->for($kunde, 'customer')->create();

        $this->expectException(QueryException::class);

        $kunde->delete();
    }
}
