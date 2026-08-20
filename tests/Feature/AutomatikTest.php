<?php

namespace Tests\Feature;

use App\Enums\DokumentArt;
use App\Enums\DokumentStand;
use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Dokument;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Support\Automatik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Was das System von selbst tut.
 *
 * Jede dieser Regeln spart einen zweiten Handgriff, den man ohnehin machen
 * würde — und jede kann in dieselbe Falle laufen: zu viel tun. Eine Automatik,
 * die Dinge ändert, die niemand angefasst hat, kostet mehr Vertrauen, als sie
 * an Arbeit spart, denn man findet den Grund nicht mehr.
 *
 * Deshalb prüft dieser Test zu jeder Regel beides: dass sie greift — und wo
 * sie ausdrücklich nicht greift.
 */
class AutomatikTest extends TestCase
{
    use RefreshDatabase;

    private function stadium(string $slug, string $name, array $daten = []): TicketStatus
    {
        return TicketStatus::factory()->create(array_merge(['slug' => $slug, 'name' => $name], $daten));
    }

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    private function mitarbeiter(): User
    {
        return User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);
    }

    private function kundenzugang(Customer $kunde): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
        ]);
    }

    private function projekt(?Customer $kunde = null): Project
    {
        return Project::factory()->for($kunde ?? Customer::factory()->create(), 'customer')->create();
    }

    private function ticket(Project $projekt, TicketStatus $stadium, array $daten = []): Ticket
    {
        return Ticket::factory()->for($projekt, 'project')->for($stadium, 'status')->create(array_merge([
            'customer_id' => $projekt->customer_id,
        ], $daten));
    }

    // -------------------------------------------------- Aus der Wartestellung

    public function test_antwortet_der_kunde_wartet_das_ticket_nicht_mehr_auf_ihn(): void
    {
        $warten = $this->stadium('warten-kunde', 'Warten auf Kunde', ['wartet_auf_kunde' => true]);
        $inArbeit = $this->stadium(TicketStatus::IN_ARBEIT, 'In Arbeit');

        $zustaendig = $this->mitarbeiter();
        $projekt = $this->projekt();
        $ticket = $this->ticket($projekt, $warten, ['assigned_to' => $zustaendig->getKey()]);

        Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $this->kundenzugang($projekt->customer)->getKey(),
            'body' => 'Hier sind die Bilder.',
            'ist_intern' => false,
        ]);

        $this->assertTrue($ticket->fresh()->status->is($inArbeit));
    }

    public function test_ohne_zustaendige_landet_es_wieder_im_stapel(): void
    {
        // "In Arbeit" wäre gelogen: an einem Ticket, für das niemand
        // eingetragen ist, arbeitet auch niemand.
        $warten = $this->stadium('warten-kunde', 'Warten auf Kunde', ['wartet_auf_kunde' => true]);
        $offen = $this->stadium(TicketStatus::OFFEN, 'Offen');
        $this->stadium(TicketStatus::IN_ARBEIT, 'In Arbeit');

        $projekt = $this->projekt();
        $ticket = $this->ticket($projekt, $warten);

        Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $this->kundenzugang($projekt->customer)->getKey(),
            'body' => 'Passt so.',
            'ist_intern' => false,
        ]);

        $this->assertTrue($ticket->fresh()->status->is($offen));
    }

    public function test_ein_kommentar_von_uns_laesst_die_wartestellung_stehen(): void
    {
        // Wir haben nachgefasst — gewartet wird trotzdem weiter auf ihn.
        $warten = $this->stadium('warten-kunde', 'Warten auf Kunde', ['wartet_auf_kunde' => true]);
        $this->stadium(TicketStatus::IN_ARBEIT, 'In Arbeit');

        $projekt = $this->projekt();
        $ticket = $this->ticket($projekt, $warten);

        Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $this->mitarbeiter()->getKey(),
            'body' => 'Wir warten noch auf die Bilder.',
            'ist_intern' => false,
        ]);

        $this->assertTrue($ticket->fresh()->status->is($warten));
    }

    public function test_ein_ticket_in_arbeit_bleibt_wo_es_ist(): void
    {
        $inArbeit = $this->stadium(TicketStatus::IN_ARBEIT, 'In Arbeit');
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $projekt = $this->projekt();
        $ticket = $this->ticket($projekt, $inArbeit);

        Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $this->kundenzugang($projekt->customer)->getKey(),
            'body' => 'Noch eine Frage dazu.',
            'ist_intern' => false,
        ]);

        $this->assertTrue($ticket->fresh()->status->is($inArbeit));
    }

    // -------------------------------------------------------- Die Zuteilung

    public function test_ein_anliegen_geht_an_den_einzigen_zustaendigen(): void
    {
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $kevin = $this->mitarbeiter();
        $projekt = $this->projekt();
        $projekt->mitarbeiter()->attach($kevin);

        $ticket = $this->ticket($projekt, TicketStatus::standard(), ['quelle' => Quelle::Kunde]);

        $this->assertSame($kevin->getKey(), $ticket->assigned_to);
    }

    public function test_bei_mehreren_zustaendigen_wird_nicht_geraten(): void
    {
        // Ein falsch zugeteiltes Ticket ist schlimmer als ein unzugeteiltes:
        // danach fühlt sich niemand mehr zuständig.
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $projekt = $this->projekt();
        $projekt->mitarbeiter()->attach([$this->mitarbeiter()->getKey(), $this->mitarbeiter()->getKey()]);

        $ticket = $this->ticket($projekt, TicketStatus::standard(), ['quelle' => Quelle::Kunde]);

        $this->assertNull($ticket->assigned_to);
    }

    public function test_von_hand_angelegte_tickets_bleiben_unangetastet(): void
    {
        // Wer intern ein Ticket ohne Zuständigen anlegt, will das so.
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $projekt = $this->projekt();
        $projekt->mitarbeiter()->attach($this->mitarbeiter());

        $ticket = $this->ticket($projekt, TicketStatus::standard(), ['quelle' => Quelle::Manuell]);

        $this->assertNull($ticket->assigned_to);
    }

    public function test_die_zuteilung_faellt_auf_den_kunden_zurueck(): void
    {
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $kevin = $this->mitarbeiter();
        $kunde = Customer::factory()->create();
        $kunde->mitarbeiter()->attach($kevin);

        $ticket = $this->ticket($this->projekt($kunde), TicketStatus::standard(), ['quelle' => Quelle::Kunde]);

        $this->assertSame($kevin->getKey(), $ticket->assigned_to);
    }

    // ------------------------------------------------- Meldung an Zuständige

    public function test_wer_ein_ticket_bekommt_erfaehrt_es(): void
    {
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $kevin = $this->mitarbeiter();
        $chef = $this->admin();
        $this->actingAs($chef);

        $ticket = $this->ticket($this->projekt(), TicketStatus::standard());
        $ticket->update(['assigned_to' => $kevin->getKey()]);

        $this->assertSame(1, $kevin->unreadNotifications()->count());
        $this->assertStringStartsWith('Für dich: ', (string) $kevin->notifications()->first()->data['title']);
    }

    public function test_wer_sich_selbst_zuteilt_bekommt_keine_meldung(): void
    {
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $kevin = $this->mitarbeiter();
        $this->actingAs($kevin);

        $ticket = $this->ticket($this->projekt(), TicketStatus::standard());
        $ticket->update(['assigned_to' => $kevin->getKey()]);

        $this->assertSame(0, $kevin->unreadNotifications()->count());
    }

    // ---------------------------------------------------------- Das Angebot

    public function test_ein_angenommenes_angebot_legt_den_auftrag_an(): void
    {
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $projekt = $this->projekt();

        $angebot = Dokument::factory()->create([
            'art' => DokumentArt::Angebot,
            'stand' => DokumentStand::Offen,
            'customer_id' => $projekt->customer_id,
            'project_id' => $projekt->getKey(),
            'titel' => 'Website-Relaunch',
        ]);

        $angebot->update(['stand' => DokumentStand::Angenommen]);

        $ticket = $angebot->fresh()->folgeticket;

        $this->assertNotNull($ticket, 'Aus der Zusage ist kein Ticket entstanden.');
        $this->assertSame('Auftrag: Website-Relaunch', $ticket->titel);
        $this->assertSame($projekt->getKey(), $ticket->project_id);
    }

    public function test_zweimal_annehmen_legt_nur_einen_auftrag_an(): void
    {
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $projekt = $this->projekt();

        $angebot = Dokument::factory()->create([
            'art' => DokumentArt::Angebot,
            'stand' => DokumentStand::Offen,
            'customer_id' => $projekt->customer_id,
            'project_id' => $projekt->getKey(),
        ]);

        $angebot->update(['stand' => DokumentStand::Angenommen]);
        $angebot->update(['stand' => DokumentStand::Offen]);
        $angebot->update(['stand' => DokumentStand::Angenommen]);

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_ohne_projekt_entsteht_kein_auftrag(): void
    {
        // Das falsche Projekt sieht der Kunde in seinem Reiseplan. Raten ist
        // hier teurer als nichts tun.
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $angebot = Dokument::factory()->create([
            'art' => DokumentArt::Angebot,
            'stand' => DokumentStand::Offen,
            'project_id' => null,
        ]);

        $angebot->update(['stand' => DokumentStand::Angenommen]);

        $this->assertSame(0, Ticket::query()->count());
        $this->assertNull($angebot->fresh()->folgeticket_id);
    }

    public function test_eine_bezahlte_rechnung_legt_nichts_an(): void
    {
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $projekt = $this->projekt();

        $rechnung = Dokument::factory()->create([
            'art' => DokumentArt::Rechnung,
            'stand' => DokumentStand::Offen,
            'customer_id' => $projekt->customer_id,
            'project_id' => $projekt->getKey(),
        ]);

        $rechnung->update(['stand' => DokumentStand::Bezahlt]);

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_der_auftrag_geht_an_den_zustaendigen_des_projekts(): void
    {
        $this->stadium(TicketStatus::OFFEN, 'Offen');

        $kevin = $this->mitarbeiter();
        $projekt = $this->projekt();
        $projekt->mitarbeiter()->attach($kevin);

        $angebot = Dokument::factory()->create([
            'art' => DokumentArt::Angebot,
            'stand' => DokumentStand::Offen,
            'customer_id' => $projekt->customer_id,
            'project_id' => $projekt->getKey(),
        ]);

        $angebot->update(['stand' => DokumentStand::Angenommen]);

        $this->assertSame($kevin->getKey(), $angebot->fresh()->folgeticket->assigned_to);
    }

    public function test_die_regeln_liegen_an_einer_stelle(): void
    {
        // Wer wissen will, was das System von selbst tut, soll eine Datei
        // aufschlagen müssen und nicht sieben Observer durchsehen.
        $this->assertTrue(method_exists(Automatik::class, 'inArbeit'));
        $this->assertTrue(method_exists(Automatik::class, 'ausDerWartestellung'));
        $this->assertTrue(method_exists(Automatik::class, 'zustaendigerFuer'));
        $this->assertTrue(method_exists(Automatik::class, 'folgeticket'));
    }
}
