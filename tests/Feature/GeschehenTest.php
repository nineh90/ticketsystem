<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Widgets\Geschehen;
use App\Filament\Widgets\TeamUeberblick;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\DashboardBesuch;
use App\Support\Ereignis;
use App\Support\Ereignisstrom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Der Ereignisstrom auf dem Dashboard.
 *
 * Zwei Dinge müssen hier halten, und beide sind nicht offensichtlich:
 *
 * Erstens die Trennung. Der Strom liest aus vier Tabellen; die Rollenregel
 * steckt in jeder der vier Unterabfragen. Wird eine davon beim Erweitern
 * vergessen, sieht ein Mitarbeiter plötzlich Kommentare aus fremden
 * Kundenprojekten — und zwar ausgerechnet auf der Startseite.
 *
 * Zweitens die Vergangenheit. Der Strom soll auch zeigen, was vor seiner
 * Einführung passiert ist. Das funktioniert nur, solange er aus den
 * bestehenden Tabellen liest und nicht aus einem eigenen Protokoll, das erst
 * ab jetzt mitschreibt.
 */
class GeschehenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Der Merker lebt im Prozess, nicht in der Anfrage — ohne das
        // Zurücksetzen trüge der zweite Test die Marke des ersten.
        DashboardBesuch::zuruecksetzen();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
    }

    private function mitarbeiter(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);
    }

    public function test_strom_zeigt_alle_vier_arten_von_ereignissen(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();
        $ticket = Ticket::factory()->create(['ticket_status_id' => $status->id]);

        Comment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $admin->id]);
        TimeEntry::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $admin->id]);
        $ticket->attachments()->create([
            'user_id' => $admin->id,
            'pfad' => 'anhaenge/1/datei.png',
            'dateiname' => 'bild.png',
            'mime' => 'image/png',
            'groesse' => 100,
        ]);

        $typen = Ereignisstrom::fuer($admin, 50)->pluck('typ');

        // Das Anlegen des Tickets protokolliert activitylog von selbst.
        $this->assertContains(Ereignis::ANGELEGT, $typen);
        $this->assertContains(Ereignis::KOMMENTAR, $typen);
        $this->assertContains(Ereignis::ZEIT, $typen);
        $this->assertContains(Ereignis::ANHANG, $typen);
    }

    public function test_strom_zeigt_was_vor_seiner_einfuehrung_geschah(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();

        $alt = Comment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'body' => 'Vor Wochen geschrieben',
        ]);
        $alt->forceFill(['created_at' => now()->subWeeks(6)])->save();

        $zitate = Ereignisstrom::fuer($admin, 50)->pluck('zitat')->filter();

        $this->assertContains('Vor Wochen geschrieben', $zitate);
    }

    public function test_mitarbeiter_sieht_nichts_aus_fremden_projekten(): void
    {
        $mitarbeiter = $this->mitarbeiter();
        $fremder = $this->admin();

        $meins = Project::factory()->create();
        $meins->mitarbeiter()->attach($mitarbeiter);

        $eigenes = Ticket::factory()->for($meins, 'project')->create();
        $fremdes = Ticket::factory()->create();

        Comment::factory()->create([
            'ticket_id' => $eigenes->id,
            'user_id' => $fremder->id,
            'body' => 'Aus meinem Projekt',
        ]);
        Comment::factory()->create([
            'ticket_id' => $fremdes->id,
            'user_id' => $fremder->id,
            'body' => 'Aus fremdem Projekt',
        ]);
        TimeEntry::factory()->create(['ticket_id' => $fremdes->id, 'user_id' => $fremder->id]);
        $fremdes->attachments()->create([
            'user_id' => $fremder->id,
            'pfad' => 'anhaenge/2/geheim.png',
            'dateiname' => 'geheim.png',
            'mime' => 'image/png',
            'groesse' => 10,
        ]);

        $strom = Ereignisstrom::fuer($mitarbeiter, 50);

        $this->assertContains('Aus meinem Projekt', $strom->pluck('zitat')->filter());
        $this->assertNotContains('Aus fremdem Projekt', $strom->pluck('zitat')->filter());

        // Und zwar in allen vier Quellen, nicht nur bei den Kommentaren.
        $this->assertEmpty(
            $strom->filter(fn (Ereignis $e) => $e->ticket?->is($fremdes)),
            'Der Strom enthält Ereignisse aus einem fremden Projekt.',
        );
    }

    public function test_umfang_andere_blendet_die_eigenen_taten_aus(): void
    {
        $admin = $this->admin();
        $kollege = $this->admin();
        $ticket = Ticket::factory()->create();

        Comment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'body' => 'Von mir',
        ]);
        Comment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $kollege->id,
            'body' => 'Vom Kollegen',
        ]);

        $zitate = Ereignisstrom::fuer($admin, 50, Ereignisstrom::ANDERE)->pluck('zitat')->filter();

        $this->assertContains('Vom Kollegen', $zitate);
        $this->assertNotContains('Von mir', $zitate);
    }

    public function test_umfang_meine_zeigt_nur_zugewiesene_tickets(): void
    {
        $admin = $this->admin();
        $meins = Ticket::factory()->create(['assigned_to' => $admin->id]);
        $anderes = Ticket::factory()->create();

        Comment::factory()->create(['ticket_id' => $meins->id, 'user_id' => $admin->id, 'body' => 'A']);
        Comment::factory()->create(['ticket_id' => $anderes->id, 'user_id' => $admin->id, 'body' => 'B']);

        $strom = Ereignisstrom::fuer($admin, 50, Ereignisstrom::MEINE);

        $this->assertTrue($strom->every(fn (Ereignis $e) => $e->ticket?->is($meins)));
    }

    public function test_typfilter_liefert_nur_die_gewaehlte_art(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        Comment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $admin->id]);
        TimeEntry::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $admin->id]);

        $nurKommentare = Ereignisstrom::fuer($admin, 50, Ereignisstrom::ALLES, Ereignis::KOMMENTAR);

        $this->assertNotEmpty($nurKommentare);
        $this->assertTrue($nurKommentare->every(fn (Ereignis $e) => $e->typ === Ereignis::KOMMENTAR));
    }

    public function test_erster_besuch_markiert_nichts_als_neu(): void
    {
        $admin = $this->admin();
        Ticket::factory()->create();

        Livewire::actingAs($admin)
            ->test(Geschehen::class)
            ->assertSuccessful()
            ->assertSet('neu', 0);

        // Der Besuch wird festgehalten, sonst wäre auch beim nächsten Mal
        // nichts neu.
        $this->assertNotNull($admin->fresh()->dashboard_gesehen_at);
    }

    public function test_was_seit_dem_letzten_besuch_kam_gilt_als_neu(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['dashboard_gesehen_at' => now()->subHour()])->save();

        $ticket = Ticket::factory()->create();
        Comment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(Geschehen::class)
            ->assertSuccessful()
            // Ticket angelegt und kommentiert — beides in dieser Stunde.
            ->assertSet('neu', 2);
    }

    public function test_widget_zeigt_kommentar_und_ticket(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['titel' => 'Kaputter Knopf']);
        Comment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'body' => 'Liegt am Zwischenspeicher',
        ]);

        Livewire::actingAs($admin)
            ->test(Geschehen::class)
            ->assertSuccessful()
            ->assertSee('hat kommentiert')
            ->assertSee('Liegt am Zwischenspeicher')
            ->assertSee('Kaputter Knopf')
            ->assertSee($ticket->kennung());
    }

    public function test_mehr_anzeigen_verlaengert_die_liste(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        Comment::factory()->count(20)->create(['ticket_id' => $ticket->id, 'user_id' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(Geschehen::class)
            ->assertSet('anzahl', 15)
            ->call('mehrAnzeigen')
            ->assertSet('anzahl', 30)
            ->assertSuccessful();
    }

    public function test_teamueberblick_bleibt_mitarbeitern_verborgen(): void
    {
        // Auch mit Projektzuordnung nicht: die Betriebszahlen sind Sache des
        // Administrators. Ein Mitarbeiter bekommt oben seine vier eigenen
        // Kacheln, alles Weitere zu seinen Projekten steht im Diagramm und im
        // Ereignisstrom.
        $mitarbeiter = $this->mitarbeiter();
        Project::factory()->create()->mitarbeiter()->attach($mitarbeiter);

        $this->actingAs($mitarbeiter);

        $this->assertFalse(TeamUeberblick::canView());
    }

    public function test_teamueberblick_zaehlt_den_ganzen_betrieb(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create(['ist_abschluss' => false]);

        // Zwei offene Tickets, die niemandem zugewiesen sind — im
        // persönlichen Überblick tauchen sie nicht auf, hier müssen sie.
        Ticket::factory()->count(2)->create(['ticket_status_id' => $status->id]);

        Livewire::actingAs($admin)
            ->test(TeamUeberblick::class)
            ->assertSuccessful()
            ->assertSee('Im Betrieb')
            ->assertSee('Offen gesamt')
            ->assertSee('2');
    }

    public function test_teamueberblick_zaehlt_ueber_sichtbarfuer(): void
    {
        // Das Widget ist zwar Administratoren vorbehalten, seine Abfragen
        // laufen aber trotzdem über sichtbarFuer. Sollte es je jemand
        // freigeben, hängt die Rollentrennung nicht allein an canView().
        $mitarbeiter = $this->mitarbeiter();
        $status = TicketStatus::factory()->create(['ist_abschluss' => false]);

        $meins = Project::factory()->create();
        $meins->mitarbeiter()->attach($mitarbeiter);

        Ticket::factory()->count(3)->for($meins, 'project')->create([
            'ticket_status_id' => $status->id,
        ]);
        Ticket::factory()->count(5)->create(['ticket_status_id' => $status->id]);

        $this->assertSame(3, Ticket::query()->sichtbarFuer($mitarbeiter)->offen()->count());
        $this->assertSame(8, Ticket::query()->offen()->count());
    }

    public function test_dashboard_laedt_mit_allen_widgets(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        Comment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $admin->id]);
        Attachment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'pfad' => 'anhaenge/1/x.png',
            'dateiname' => 'x.png',
            'mime' => 'image/png',
            'groesse' => 1,
        ]);

        $this->actingAs($admin)->get('/')->assertOk();
    }
}
