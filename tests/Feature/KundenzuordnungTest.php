<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Support\Sichtbarkeit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zuordnung ganzer Kunden.
 *
 * Zweiter Weg neben der Projektzuordnung. Der eigentliche Gewinn steckt in
 * test_neues_projekt_ist_sofort_sichtbar: wer einem Kunden zugeordnet ist,
 * sieht dessen künftige Projekte automatisch — bei der Projektzuordnung muss
 * man jedes Mal daran denken.
 */
class KundenzuordnungTest extends TestCase
{
    use RefreshDatabase;

    private function mitarbeiter(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);
    }

    public function test_kundenzuordnung_zeigt_alle_projekte_des_kunden(): void
    {
        $nutzer = $this->mitarbeiter();

        $meiner = Customer::factory()->create();
        $meiner->mitarbeiter()->attach($nutzer);

        Project::factory()->count(3)->for($meiner, 'customer')->create();
        Project::factory()->create(); // fremder Kunde

        $sichtbar = Project::query()->sichtbarFuer($nutzer)->pluck('customer_id')->unique();

        $this->assertSame(3, Project::query()->sichtbarFuer($nutzer)->count());
        $this->assertSame([$meiner->id], $sichtbar->values()->all());
    }

    public function test_neues_projekt_ist_sofort_sichtbar(): void
    {
        // Das ist der Grund für die Kundenzuordnung: bei der Projektzuordnung
        // müsste man den Mitarbeiter jetzt erneut eintragen.
        $nutzer = $this->mitarbeiter();

        $kunde = Customer::factory()->create();
        $kunde->mitarbeiter()->attach($nutzer);

        $this->assertSame(0, Project::query()->sichtbarFuer($nutzer)->count());

        Project::factory()->for($kunde, 'customer')->create();

        $this->assertSame(1, Project::query()->sichtbarFuer($nutzer)->count());
    }

    public function test_tickets_des_kunden_sind_sichtbar(): void
    {
        $nutzer = $this->mitarbeiter();
        $status = TicketStatus::factory()->create();

        $kunde = Customer::factory()->create();
        $kunde->mitarbeiter()->attach($nutzer);

        $meins = Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create(['ticket_status_id' => $status->id]);
        $fremdes = Ticket::factory()->create(['ticket_status_id' => $status->id]);

        $sichtbar = Ticket::query()->sichtbarFuer($nutzer)->pluck('id');

        $this->assertContains($meins->id, $sichtbar);
        $this->assertNotContains($fremdes->id, $sichtbar);
    }

    public function test_beide_wege_gelten_nebeneinander(): void
    {
        $nutzer = $this->mitarbeiter();

        $ueberKunde = Customer::factory()->create();
        $ueberKunde->mitarbeiter()->attach($nutzer);
        Project::factory()->for($ueberKunde, 'customer')->create();

        // Einzelnes Projekt bei einem Kunden, dem er NICHT zugeordnet ist.
        $einzeln = Project::factory()->create();
        $einzeln->mitarbeiter()->attach($nutzer);

        Project::factory()->create(); // weder noch

        $this->assertSame(2, Project::query()->sichtbarFuer($nutzer)->count());
    }

    public function test_policy_kennt_dieselben_zwei_wege(): void
    {
        // Sonst zeigte die Liste einen Datensatz an, der sich nicht öffnen
        // lässt — der unangenehmste Fehler in dieser Ecke.
        $nutzer = $this->mitarbeiter();
        $status = TicketStatus::factory()->create();

        $kunde = Customer::factory()->create();
        $kunde->mitarbeiter()->attach($nutzer);

        $projekt = Project::factory()->for($kunde, 'customer')->create();
        $ticket = Ticket::factory()->for($projekt, 'project')
            ->create(['ticket_status_id' => $status->id]);

        $this->assertTrue($nutzer->can('view', $kunde));
        $this->assertTrue($nutzer->can('view', $projekt));
        $this->assertTrue($nutzer->can('view', $ticket));
        $this->assertTrue($nutzer->can('update', $ticket));
    }

    public function test_kunde_erscheint_in_der_kundenliste(): void
    {
        $nutzer = $this->mitarbeiter();

        $meiner = Customer::factory()->create();
        $meiner->mitarbeiter()->attach($nutzer);
        Customer::factory()->create();

        $sichtbar = Customer::query()->sichtbarFuer($nutzer)->pluck('id');

        $this->assertSame([$meiner->id], $sichtbar->all());
    }

    public function test_kundenzuordnung_zaehlt_als_zuordnung(): void
    {
        // Der Hinweis "keine Zuordnung" darf nicht mehr erscheinen, sobald ein
        // Kunde zugewiesen ist — auch wenn noch kein Projekt existiert.
        $nutzer = $this->mitarbeiter();
        Customer::factory()->create()->mitarbeiter()->attach($nutzer);

        $this->actingAs($nutzer);

        $this->assertFalse(Sichtbarkeit::ohneProjekte());
    }

    public function test_zuordnungsfelder_sind_beim_bearbeiten_sichtbar(): void
    {
        // Der Fehler, der Kevin die Projekte gekostet hat: beim Bearbeiten
        // liefert $get('rolle') den gecasteten Enum-Fall, beim Anlegen den
        // String aus ->default(). Ein === gegen den String schlug deshalb
        // beim Bearbeiten fehl, der ganze Abschnitt blieb unsichtbar — und
        // damit ließ sich einem bestehenden Mitarbeiter gar nichts zuweisen.
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
        $mitarbeiter = $this->mitarbeiter();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Users\Pages\EditUser::class, [
                'record' => $mitarbeiter->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Ganze Kunden')
            ->assertSee('Einzelne Projekte');
    }

    public function test_zuordnungsfelder_fehlen_beim_admin(): void
    {
        // Für Administratoren wären sie bedeutungslos — sie sehen ohnehin
        // alles.
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Users\Pages\EditUser::class, [
                'record' => $admin->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertDontSee('Ganze Kunden');
    }

    public function test_ohne_jede_zuordnung_bleibt_alles_leer(): void
    {
        $nutzer = $this->mitarbeiter();
        $status = TicketStatus::factory()->create();

        Ticket::factory()->create(['ticket_status_id' => $status->id]);

        $this->assertSame(0, Project::query()->sichtbarFuer($nutzer)->count());
        $this->assertSame(0, Ticket::query()->sichtbarFuer($nutzer)->count());
        $this->assertSame(0, Customer::query()->sichtbarFuer($nutzer)->count());
    }
}
