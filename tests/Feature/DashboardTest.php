<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Pages\Betrieb;
use App\Filament\Pages\MeinBereich;
use App\Filament\Widgets\MeineTickets;
use App\Filament\Widgets\MeinUeberblick;
use App\Filament\Widgets\TeamUeberblick;
use App\Filament\Widgets\TicketsVerteilung;
use App\Filament\Widgets\ZeitenVerteilung;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
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

    public function test_betrieb_liegt_auf_einer_eigenen_seite(): void
    {
        $admin = $this->admin();

        $status = TicketStatus::factory()->create();
        Ticket::factory()->create([
            'ticket_status_id' => $status->id,
            'assigned_to' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/betrieb')->assertOk();
    }

    public function test_die_beiden_seiten_teilen_sich_die_karten_ohne_ueberschneidung(): void
    {
        // Der eigentliche Zweck der Trennung. Ein Widget auf beiden Seiten
        // wäre kein Fehler, den man sieht — es sähe nur wieder nach der alten
        // Wand aus Karten aus, und zwar zweimal.
        $meins = (new MeinBereich)->getWidgets();
        $betrieb = (new Betrieb)->getWidgets();

        $this->assertSame([], array_intersect($meins, $betrieb));

        $this->assertContains(MeinUeberblick::class, $meins);
        $this->assertContains(MeineTickets::class, $meins);
        $this->assertNotContains(TeamUeberblick::class, $meins);

        $this->assertContains(TeamUeberblick::class, $betrieb);
        $this->assertContains(TicketsVerteilung::class, $betrieb);
        $this->assertContains(ZeitenVerteilung::class, $betrieb);
        $this->assertNotContains(MeineTickets::class, $betrieb);
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
            ->test(TicketsVerteilung::class)
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

    public function test_diagramm_bleibt_ohne_zuordnung_weg(): void
    {
        // Ohne Kunde und ohne Projekt gäbe es nichts zu verteilen; das
        // Diagramm zeigte dann eine leere Achse ohne erkennbaren Grund.
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $this->actingAs($mitarbeiter);

        $this->assertFalse(TicketsVerteilung::canView());
    }

    public function test_mitarbeiter_sieht_die_verteilung_seiner_projekte(): void
    {
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $status = TicketStatus::factory()->create(['ist_abschluss' => false]);

        $meins = Project::factory()->create(['name' => 'Mein Projekt']);
        $meins->mitarbeiter()->attach($mitarbeiter);
        Ticket::factory()->count(2)->for($meins, 'project')->create([
            'ticket_status_id' => $status->id,
        ]);

        // Fremdes Projekt mit mehr Tickets — es darf im Diagramm nicht
        // auftauchen, sonst verriete die Verteilung fremde Auslastung.
        $fremd = Project::factory()->create(['name' => 'Fremdes Projekt']);
        Ticket::factory()->count(4)->for($fremd, 'project')->create([
            'ticket_status_id' => $status->id,
        ]);

        $this->actingAs($mitarbeiter);
        $this->assertTrue(TicketsVerteilung::canView());

        Livewire::actingAs($mitarbeiter)
            ->test(TicketsVerteilung::class)
            ->assertSuccessful()
            ->assertSee('Offene Tickets je Projekt')
            ->assertSee('Mein Projekt')
            ->assertDontSee('Fremdes Projekt');
    }

    public function test_zeitdiagramm_summiert_je_kunde(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();

        $viel = Customer::factory()->create(['name' => 'Vielarbeiter']);
        $wenig = Customer::factory()->create(['name' => 'Wenigarbeiter']);
        // Ein Kunde ganz ohne Buchungen gehört nicht in eine Verteilung der
        // Zeit — er stünde sonst als Balken der Höhe null in der Achse.
        Customer::factory()->create(['name' => 'Ohnezeit']);

        $ticketA = Ticket::factory()
            ->for(Project::factory()->for($viel, 'customer'), 'project')
            ->create(['ticket_status_id' => $status->id]);
        $ticketB = Ticket::factory()
            ->for(Project::factory()->for($wenig, 'customer'), 'project')
            ->create(['ticket_status_id' => $status->id]);

        // Zwei Buchungen auf denselben Kunden, damit die Summe geprüft wird
        // und nicht nur die letzte Zeile.
        TimeEntry::factory()->create(['ticket_id' => $ticketA->id, 'minuten' => 90]);
        TimeEntry::factory()->create(['ticket_id' => $ticketA->id, 'minuten' => 45]);
        TimeEntry::factory()->create(['ticket_id' => $ticketB->id, 'minuten' => 30]);

        Livewire::actingAs($admin)
            ->test(ZeitenVerteilung::class)
            ->assertSuccessful()
            ->assertSee('Erfasste Zeit je Kunde')
            ->assertSee('Vielarbeiter')
            ->assertSee('Wenigarbeiter')
            ->assertDontSee('Ohnezeit');

        $daten = $this->datenVon(new ZeitenVerteilung);

        // 135 Minuten sind 2,25 Stunden, und der Vielarbeiter steht vorn.
        $this->assertSame(['Vielarbeiter', 'Wenigarbeiter'], $daten['labels']);
        $this->assertSame([2.25, 0.5], $daten['datasets'][0]['data']);
    }

    public function test_zeitdiagramm_zeigt_mitarbeitern_nur_ihre_projekte(): void
    {
        // Der eigentliche Punkt des Widgets: gebuchte Zeit sagt, was ein
        // Kunde kostet. Fremde Zeiten hier durchzulassen wäre dasselbe Leck
        // wie fremde Tickets in der Liste, nur schwerer zu bemerken.
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $status = TicketStatus::factory()->create();

        $meins = Project::factory()->create(['name' => 'Mein Projekt']);
        $meins->mitarbeiter()->attach($mitarbeiter);
        $meinTicket = Ticket::factory()->for($meins, 'project')->create([
            'ticket_status_id' => $status->id,
        ]);
        TimeEntry::factory()->create(['ticket_id' => $meinTicket->id, 'minuten' => 60]);

        $fremd = Project::factory()->create(['name' => 'Fremdes Projekt']);
        $fremdesTicket = Ticket::factory()->for($fremd, 'project')->create([
            'ticket_status_id' => $status->id,
        ]);
        TimeEntry::factory()->create(['ticket_id' => $fremdesTicket->id, 'minuten' => 600]);

        Livewire::actingAs($mitarbeiter)
            ->test(ZeitenVerteilung::class)
            ->assertSuccessful()
            ->assertSee('Erfasste Zeit je Projekt')
            ->assertSee('Mein Projekt')
            ->assertDontSee('Fremdes Projekt');

        $this->actingAs($mitarbeiter);
        $daten = $this->datenVon(new ZeitenVerteilung);

        // Nur die eigene Stunde, nicht die zehn des fremden Projekts.
        $this->assertSame([1.0], $daten['datasets'][0]['data']);
    }

    public function test_zeitdiagramm_grenzt_den_zeitraum_ein(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();

        $kunde = Customer::factory()->create(['name' => 'Langjährig']);
        $ticket = Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create(['ticket_status_id' => $status->id]);

        TimeEntry::factory()->create([
            'ticket_id' => $ticket->id,
            'gestartet_am' => now()->startOfMonth()->addHours(9),
            'minuten' => 60,
        ]);
        TimeEntry::factory()->create([
            'ticket_id' => $ticket->id,
            'gestartet_am' => now()->subMonthNoOverflow()->startOfMonth()->addHours(9),
            'minuten' => 120,
        ]);

        $this->actingAs($admin);

        $gesamt = new ZeitenVerteilung;
        $this->assertSame([3.0], $this->datenVon($gesamt)['datasets'][0]['data']);

        $monat = new ZeitenVerteilung;
        $monat->filter = 'monat';
        $this->assertSame([1.0], $this->datenVon($monat)['datasets'][0]['data']);

        // Der letzte Monat darf den laufenden nicht mitnehmen — das ist die
        // Grenze, an der ein <= statt < den ersten Tag doppelt zählen würde.
        $letzter = new ZeitenVerteilung;
        $letzter->filter = 'letzter-monat';
        $this->assertSame([2.0], $this->datenVon($letzter)['datasets'][0]['data']);
    }

    public function test_zeitdiagramm_sagt_es_wenn_der_zeitraum_leer_ist(): void
    {
        // Ohne diesen Fall stünde bei "Letzter Monat" eine Achse von 0 bis
        // 1 h ohne einen einzigen Balken — was aussieht, als sei das
        // Diagramm kaputt, und nicht, als sei nichts gebucht worden.
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();

        $ticket = Ticket::factory()->create(['ticket_status_id' => $status->id]);
        TimeEntry::factory()->create([
            'ticket_id' => $ticket->id,
            'gestartet_am' => now()->startOfMonth()->addHours(9),
            'minuten' => 60,
        ]);

        $this->actingAs($admin);

        $letzter = new ZeitenVerteilung;
        $letzter->filter = 'letzter-monat';

        $this->assertSame([], $this->datenVon($letzter));
        $this->assertTrue($letzter->isEmpty());
    }

    public function test_zeitdiagramm_bleibt_ohne_zuordnung_weg(): void
    {
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $this->actingAs($mitarbeiter);

        $this->assertFalse(ZeitenVerteilung::canView());
    }

    /**
     * Die Rohdaten eines Diagramms, an den geschützten getData() vorbei.
     *
     * Über assertSee allein wäre nur zu sehen, dass ein Name im Diagramm
     * steht — nicht, mit welchem Wert. Und der Wert ist hier die Aussage.
     *
     * @return array<string, mixed>
     */
    private function datenVon(ZeitenVerteilung $widget): array
    {
        // Ohne setAccessible(): seit PHP 8.1 wirkungslos, seit 8.5 verworfen.
        return (new \ReflectionMethod($widget, 'getData'))->invoke($widget);
    }
}
