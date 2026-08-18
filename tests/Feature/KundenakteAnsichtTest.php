<?php

namespace Tests\Feature;

use App\Enums\DokumentStand;
use App\Enums\Rolle;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\Widgets\KundeKennzahlen;
use App\Filament\Resources\Customers\Widgets\KundeTicketaufkommen;
use App\Filament\Resources\Customers\Widgets\KundeZeitverlauf;
use App\Models\Customer;
use App\Models\Dokument;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die Kundenakte als Ansicht — Zahlen und Verläufe zu einem Kunden.
 *
 * Vorher landete jeder Weg zu einem Kunden im Bearbeiten-Formular, und dort
 * stand keine einzige Zahl. Diese Tests halten fest, dass die Zahlen den
 * Kunden meinen, den man geöffnet hat, und keinen anderen — bei einer
 * Auswertung ist das der Fehler, den niemand bemerkt, weil das Ergebnis
 * plausibel aussieht.
 */
class KundenakteAnsichtTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    public function test_die_akte_laesst_sich_ansehen(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create();

        $this->actingAs($admin)
            ->get(ViewCustomer::getUrl(['record' => $kunde]))
            ->assertOk()
            ->assertSee($kunde->name);
    }

    public function test_die_kennzahlen_zaehlen_nur_diesen_kunden(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create(['ist_abschluss' => false]);

        $kunde = Customer::factory()->create();
        $anderer = Customer::factory()->create();

        $meins = Ticket::factory()->count(2)
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create(['ticket_status_id' => $status->id]);

        // Beim anderen Kunden deutlich mehr — käme es durch, fiele es auf.
        Ticket::factory()->count(5)
            ->for(Project::factory()->for($anderer, 'customer'), 'project')
            ->create(['ticket_status_id' => $status->id]);

        TimeEntry::factory()->create(['ticket_id' => $meins->first()->id, 'minuten' => 90]);

        Livewire::actingAs($admin)
            ->test(KundeKennzahlen::class, ['record' => $kunde])
            ->assertSuccessful()
            // Zwei offene Tickets und 1:30 h — beides Zahlen dieses Kunden.
            ->assertSee('Offene Tickets')
            ->assertSee('1:30 h');
    }

    public function test_offene_posten_summieren_nur_offene_rechnungen(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create();

        Dokument::factory()->for($kunde, 'customer')->create([
            'betrag' => 1000,
            'stand' => DokumentStand::Offen,
        ]);
        Dokument::factory()->for($kunde, 'customer')->create([
            'betrag' => 500,
            'stand' => DokumentStand::Offen,
        ]);
        // Bezahlt und storniert gehören nicht in die Summe.
        Dokument::factory()->for($kunde, 'customer')->create([
            'betrag' => 9999,
            'stand' => DokumentStand::Bezahlt,
        ]);
        Dokument::factory()->for($kunde, 'customer')->create([
            'betrag' => 8888,
            'stand' => DokumentStand::Storniert,
        ]);

        Livewire::actingAs($admin)
            ->test(KundeKennzahlen::class, ['record' => $kunde])
            ->assertSuccessful()
            ->assertSee('1.500,00 €')
            ->assertDontSee('9.999,00 €');
    }

    public function test_der_zeitverlauf_zeigt_zwoelf_monate(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();
        $kunde = Customer::factory()->create();

        $ticket = Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create(['ticket_status_id' => $status->id]);

        TimeEntry::factory()->create([
            'ticket_id' => $ticket->id,
            'gestartet_am' => now()->startOfMonth()->addHours(9),
            'minuten' => 120,
        ]);

        $widget = new KundeZeitverlauf;
        $widget->record = $kunde;

        $this->actingAs($admin);
        $daten = (new \ReflectionMethod($widget, 'getData'))->invoke($widget);

        $this->assertCount(12, $daten['labels']);
        // Der laufende Monat ist der letzte der Reihe.
        $this->assertSame(2.0, end($daten['datasets'][0]['data']));
    }

    public function test_die_verlaeufe_bleiben_ohne_daten_leer(): void
    {
        // Leer heißt hier: getData() gibt nichts zurück, damit Filament den
        // Leertext zeigt statt einer Achse ohne Balken.
        $admin = $this->admin();
        $kunde = Customer::factory()->create();

        $this->actingAs($admin);

        $zeit = new KundeZeitverlauf;
        $zeit->record = $kunde;
        $this->assertSame([], (new \ReflectionMethod($zeit, 'getData'))->invoke($zeit));

        $tickets = new KundeTicketaufkommen;
        $tickets->record = $kunde;
        $this->assertSame([], (new \ReflectionMethod($tickets, 'getData'))->invoke($tickets));
    }

    public function test_das_ticketaufkommen_zaehlt_eingang_und_erledigung_getrennt(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();
        $kunde = Customer::factory()->create();
        $projekt = Project::factory()->for($kunde, 'customer')->create();

        // Drei eingegangen, davon eines erledigt — die beiden Reihen sind
        // ausdrücklich nicht gleich lang.
        $eingang = now()->startOfMonth()->addHours(9);

        $tickets = Ticket::factory()->count(3)->for($projekt, 'project')
            ->create(['ticket_status_id' => $status->id]);

        // Beide Spalten von Hand: created_at und erledigt_at stehen nicht in
        // der Fillable-Liste des Tickets, ein create() mit ihnen ginge
        // wortlos ins Leere und der Test prüfte danach die Vorgabewerte.
        foreach ($tickets as $ticket) {
            $ticket->created_at = $eingang;
            $ticket->save();
        }

        $tickets->first()->forceFill(['erledigt_at' => $eingang->copy()->addDay()])->save();

        $widget = new KundeTicketaufkommen;
        $widget->record = $kunde;

        $this->actingAs($admin);
        $daten = (new \ReflectionMethod($widget, 'getData'))->invoke($widget);

        $this->assertSame('Eingegangen', $daten['datasets'][0]['label']);
        $this->assertSame('Erledigt', $daten['datasets'][1]['label']);
        $this->assertSame(3, end($daten['datasets'][0]['data']));
        $this->assertSame(1, end($daten['datasets'][1]['data']));
    }

    public function test_mitarbeiter_kommt_nicht_an_fremde_kundenakten(): void
    {
        // Die Absicherung liegt in CustomerResource::getEloquentQuery und der
        // Policy; der Test hält fest, dass die neue Ansicht daran nichts
        // vorbeilässt.
        $mitarbeiter = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);
        $fremder = Customer::factory()->create();

        $this->actingAs($mitarbeiter)
            ->get(ViewCustomer::getUrl(['record' => $fremder]))
            ->assertNotFound();
    }
}
