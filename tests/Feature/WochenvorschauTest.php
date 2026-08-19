<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Widgets\Wochenvorschau;
use App\Models\Customer;
use App\Models\Meilenstein;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\Treffen;
use App\Models\User;
use App\Support\Termin;
use App\Support\Wochenplan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die Wochenvorschau auf der Brücke.
 *
 * Sie zieht aus vier Tabellen zusammen, und genau daran hängt das Risiko:
 * jede Quelle muss durch ihr eigenes sichtbarFuer laufen. Eine Vorschau, die
 * "nur schnell" direkt abfragt, ist die Stelle, an der ein Mitarbeiter den
 * Kunden eines anderen zu sehen bekommt — und man merkt es nie, weil eine
 * Übersicht niemand Zeile für Zeile nachzählt.
 */
class WochenvorschauTest extends TestCase
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

    public function test_sammelt_aus_allen_vier_quellen(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $projekt = Project::factory()->for($kunde, 'customer')->create();
        $status = TicketStatus::factory()->create(['ist_abschluss' => false]);

        Treffen::factory()->for($kunde, 'customer')->create([
            'beginnt_am' => now()->addDays(2)->setTime(10, 0),
        ]);

        Meilenstein::create([
            'project_id' => $projekt->getKey(),
            'titel' => 'Entwurf steht',
            'faellig_am' => today()->addDays(3),
        ]);

        Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $status->getKey(),
            'faellig_am' => today()->addDays(4),
        ]);

        $arten = Wochenplan::fuer($admin)->pluck('art');

        $this->assertContains(Termin::TREFFEN, $arten);
        $this->assertContains(Termin::MEILENSTEIN, $arten);
        $this->assertContains(Termin::TICKET, $arten);
    }

    /**
     * Der eigentliche Grund für diesen Test: jede Quelle geht durch ihr
     * eigenes sichtbarFuer. Ein Mitarbeiter ohne Zuordnung sieht nichts.
     */
    public function test_mitarbeiter_sieht_fremde_termine_nicht(): void
    {
        $kevin = $this->mitarbeiter();
        $kunde = Customer::factory()->create();

        Treffen::factory()->for($kunde, 'customer')->create([
            'titel' => 'Fremdes Treffen',
            'beginnt_am' => now()->addDays(2),
        ]);

        $this->assertSame(0, Wochenplan::fuer($kevin)->count());
    }

    public function test_mitarbeiter_sieht_die_termine_seiner_kunden(): void
    {
        $kevin = $this->mitarbeiter();
        $kunde = Customer::factory()->create();
        $kunde->mitarbeiter()->attach($kevin);

        Treffen::factory()->for($kunde, 'customer')->create([
            'titel' => 'Mein Kunde',
            'beginnt_am' => now()->addDays(2),
        ]);

        $this->assertSame(1, Wochenplan::fuer($kevin)->count());
    }

    public function test_was_weiter_weg_liegt_bleibt_draussen(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create();

        Treffen::factory()->for($kunde, 'customer')->create([
            'titel' => 'In drei Wochen',
            'beginnt_am' => now()->addWeeks(3),
        ]);

        $this->assertSame(0, Wochenplan::fuer($admin)->count());
    }

    public function test_abgesagte_treffen_stehen_nicht_in_der_vorschau(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create();

        Treffen::factory()->for($kunde, 'customer')->abgesagt()->create([
            'beginnt_am' => now()->addDays(2),
        ]);

        $this->assertSame(0, Wochenplan::fuer($admin)->count());
    }

    /** Erledigte Meilensteine sind keine Termine mehr. */
    public function test_erledigte_meilensteine_fallen_raus(): void
    {
        $admin = $this->admin();
        $projekt = Project::factory()->create();

        Meilenstein::create([
            'project_id' => $projekt->getKey(),
            'titel' => 'Längst fertig',
            'faellig_am' => today()->addDays(2),
            'erledigt_at' => now(),
        ]);

        $this->assertSame(0, Wochenplan::fuer($admin)->count());
    }

    public function test_nach_tagen_gruppiert(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create();

        $tag = now()->addDays(2);

        Treffen::factory()->for($kunde, 'customer')->create(['beginnt_am' => $tag->copy()->setTime(9, 0)]);
        Treffen::factory()->for($kunde, 'customer')->create(['beginnt_am' => $tag->copy()->setTime(15, 0)]);

        $tage = Wochenplan::jeTag($admin);

        $this->assertCount(1, $tage);
        $this->assertCount(2, $tage->first());
    }

    /**
     * "Diese Woche steht nichts an" ist eine Antwort. Eine leere Karte ist
     * ein Zweifel daran, ob sie geladen hat.
     */
    public function test_sagt_ausdruecklich_wenn_nichts_ansteht(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        Livewire::test(Wochenvorschau::class)
            ->assertOk()
            ->assertSee('Diese Woche steht nichts an');
    }

    public function test_zeigt_die_termine_mit_uhrzeit_und_kunde(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create(['name' => 'KE!N EINZELFALL e.V.']);

        Treffen::factory()->for($kunde, 'customer')->create([
            'titel' => 'Abnahme der Startseite',
            'beginnt_am' => now()->addDays(2)->setTime(14, 0),
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(Wochenvorschau::class)
            ->assertOk()
            ->assertSee('Abnahme der Startseite')
            ->assertSee('KE!N EINZELFALL e.V.')
            ->assertSee('14:00');
    }
}
