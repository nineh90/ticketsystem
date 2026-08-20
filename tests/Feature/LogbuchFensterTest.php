<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Widgets\MeinUeberblick;
use App\Filament\Widgets\TeamUeberblick;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Dauer;
use App\Support\Logbuch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die Rückseite der Zeit-Kacheln.
 *
 * Eine Zahl, die man anklicken kann, ist ein Versprechen — dasselbe wie bei
 * den Kacheln, die auf eine Liste führen (siehe KachelnFuehrenZurListeTest):
 * was aufgeht, muss die Zahl erklären, die daneben steht. Deshalb prüft
 * dieser Test vor allem zwei Dinge, die still auseinanderlaufen können:
 *
 *  - dass Summe und Auflistung dieselbe Menge meinen, und
 *  - dass im Fenster nichts steht, was der Betrachter nicht sehen darf.
 */
class LogbuchFensterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    private function mitarbeiter(): User
    {
        return User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);
    }

    /** Ein Ticket, das dem Mitarbeiter über seinen Kunden gehört. */
    private function ticketFuer(?User $mitarbeiter = null): Ticket
    {
        $kunde = Customer::factory()->create();

        if ($mitarbeiter) {
            $kunde->mitarbeiter()->attach($mitarbeiter);
        }

        return Ticket::factory()
            ->for(TicketStatus::factory()->create(), 'status')
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create(['customer_id' => $kunde->id]);
    }

    private function gebucht(User $wer, Ticket $ticket, int $minuten, ?string $was = null, ?string $wann = null): TimeEntry
    {
        $start = $wann ? now()->parse($wann) : now()->setTime(9, 0);

        return TimeEntry::create([
            'ticket_id' => $ticket->id,
            'user_id' => $wer->id,
            'gestartet_am' => $start,
            'beendet_am' => $start->copy()->addMinutes($minuten),
            'minuten' => $minuten,
            'beschreibung' => $was,
        ]);
    }

    public function test_zeit_heute_zeigt_wer_wann_woran_gearbeitet_hat(): void
    {
        $admin = $this->admin();
        $kollege = $this->mitarbeiter();
        $ticket = $this->ticketFuer();

        $this->gebucht($kollege, $ticket, 85, 'Formular umgebaut');

        Livewire::actingAs($admin)
            ->test(TeamUeberblick::class)
            ->call('logbuchOeffnen')
            ->assertSuccessful()
            ->assertSee($kollege->name)
            ->assertSee($ticket->kennung())
            ->assertSee('Formular umgebaut')
            ->assertSee('1:25 h');
    }

    public function test_das_fenster_bleibt_leer_bis_es_geoeffnet_wird(): void
    {
        // Das Widget zeichnet sich alle fünf Sekunden neu. Liefe die Abfrage
        // dabei jedes Mal mit, zahlte jeder offene Tab dafür — für ein
        // Fenster, das an den meisten Tagen niemand aufmacht.
        $admin = $this->admin();

        $this->gebucht($this->mitarbeiter(), $this->ticketFuer(), 30, 'Formular umgebaut');

        Livewire::actingAs($admin)
            ->test(TeamUeberblick::class)
            ->assertSuccessful()
            ->assertDontSee('Formular umgebaut');
    }

    public function test_die_auflistung_ergibt_die_summe_auf_der_kachel(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticketFuer();

        $this->gebucht($admin, $ticket, 45, null, 'today 08:00');
        $this->gebucht($this->mitarbeiter(), $ticket, 75, null, 'today 10:00');

        // Gestern gehört nicht dazu — die Kachel heißt "Zeit heute".
        $this->gebucht($admin, $ticket, 120, null, 'yesterday 09:00');

        $summe = (int) Logbuch::betriebHeuteAbfrage($admin)->sum('minuten');

        $this->assertSame(120, $summe);
        $this->assertSame($summe, (int) Logbuch::betriebHeute($admin)->sum('minuten'));

        Livewire::actingAs($admin)
            ->test(TeamUeberblick::class)
            ->call('logbuchOeffnen')
            ->assertSee(Dauer::alsStunden($summe));
    }

    public function test_laufende_uhr_steht_in_der_liste_aber_nicht_in_der_summe(): void
    {
        // Eine laufende Buchung hat minuten noch auf 0 stehen. Sie
        // wegzulassen wäre falsch — dann fehlte im Fenster genau die Arbeit,
        // die gerade passiert; sie mitzurechnen wäre es auch, denn dann sagte
        // die Liste mehr als die Kachel darüber.
        $admin = $this->admin();
        $ticket = $this->ticketFuer();

        $this->gebucht($admin, $ticket, 60, null, 'today 08:00');

        TimeEntry::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'gestartet_am' => now()->subMinutes(20),
        ]);

        $this->assertSame(60, (int) Logbuch::betriebHeuteAbfrage($admin)->sum('minuten'));

        Livewire::actingAs($admin)
            ->test(TeamUeberblick::class)
            ->call('logbuchOeffnen')
            ->assertSee('läuft')
            ->assertSee('1:00 h');
    }

    public function test_mein_logbuch_zeigt_nur_die_eigenen_buchungen(): void
    {
        $nutzer = $this->mitarbeiter();
        $kollege = $this->mitarbeiter();
        $ticket = $this->ticketFuer($nutzer);

        $this->gebucht($nutzer, $ticket, 30, 'Meine Buchung');
        $this->gebucht($kollege, $ticket, 30, 'Fremde Buchung');

        Livewire::actingAs($nutzer)
            ->test(MeinUeberblick::class)
            ->call('logbuchOeffnen')
            ->assertSuccessful()
            ->assertSee('Meine Buchung')
            ->assertDontSee('Fremde Buchung');
    }

    public function test_mein_logbuch_reicht_bis_montag_und_nicht_weiter(): void
    {
        $nutzer = $this->mitarbeiter();
        $ticket = $this->ticketFuer($nutzer);

        $this->gebucht($nutzer, $ticket, 30, 'Diese Woche', today()->startOfWeek()->setTime(9, 0)->toDateTimeString());
        $this->gebucht($nutzer, $ticket, 30, 'Letzte Woche', today()->startOfWeek()->subDay()->setTime(9, 0)->toDateTimeString());

        Livewire::actingAs($nutzer)
            ->test(MeinUeberblick::class)
            ->call('logbuchOeffnen')
            ->assertSee('Diese Woche')
            ->assertDontSee('Letzte Woche');
    }

    public function test_fremde_projekte_bleiben_im_betriebsfenster_aussen_vor(): void
    {
        // TeamUeberblick sieht ohnehin nur ein Administrator. Die Abfrage
        // dahinter filtert trotzdem über die sichtbaren Tickets — sonst
        // hinge die Trennung allein an canView(), und die ist beim nächsten
        // "zeig das doch auch dem Team" lautlos weg.
        $nutzer = $this->mitarbeiter();
        $kollege = $this->mitarbeiter();

        $meins = $this->ticketFuer($nutzer);
        $fremd = $this->ticketFuer();

        $sichtbar = $this->gebucht($kollege, $meins, 30);
        $this->gebucht($kollege, $fremd, 30);

        $this->assertSame([$sichtbar->id], Logbuch::betriebHeute($nutzer)->modelKeys());
    }
}
