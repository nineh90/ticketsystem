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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BerechtigungenTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_mitarbeiter_darf_fremdes_ticket_nicht_oeffnen(): void
    {
        $mitarbeiter = $this->mitarbeiter();
        $fremdes = Ticket::factory()->create();

        $this->assertFalse($mitarbeiter->can('view', $fremdes));
        $this->assertFalse($mitarbeiter->can('update', $fremdes));
    }

    public function test_mitarbeiter_darf_ticket_im_eigenen_projekt_bearbeiten(): void
    {
        $mitarbeiter = $this->mitarbeiter();
        $projekt = Project::factory()->create();
        $projekt->mitarbeiter()->attach($mitarbeiter);

        $ticket = Ticket::factory()->for($projekt, 'project')->create();

        $this->assertTrue($mitarbeiter->can('view', $ticket));
        $this->assertTrue($mitarbeiter->can('update', $ticket));

        // Löschen bleibt trotzdem beim Admin — es nimmt Kommentare und
        // Zeitbuchungen mit.
        $this->assertFalse($mitarbeiter->can('delete', $ticket));
    }

    public function test_nur_admin_verwaltet_kunden_und_projekte(): void
    {
        $mitarbeiter = $this->mitarbeiter();
        $admin = $this->admin();

        $this->assertFalse($mitarbeiter->can('create', Customer::class));
        $this->assertFalse($mitarbeiter->can('create', Project::class));
        $this->assertTrue($admin->can('create', Customer::class));
        $this->assertTrue($admin->can('create', Project::class));
    }

    public function test_nur_admin_verwaltet_nutzer(): void
    {
        // Das ist die Stelle, an der jemand sich selbst zum Admin machen
        // könnte.
        $this->assertFalse($this->mitarbeiter()->can('viewAny', User::class));
        $this->assertTrue($this->admin()->can('viewAny', User::class));
    }

    public function test_admin_kann_sich_nicht_selbst_loeschen(): void
    {
        // Wäre er der einzige Admin, könnte danach niemand mehr Freigaben
        // erteilen — und weil panel_zugang standardmäßig false ist, käme man
        // auch über ein neues Konto nicht mehr herein.
        $admin = $this->admin();
        $anderer = $this->admin();

        $this->assertFalse($admin->can('delete', $admin));
        $this->assertTrue($admin->can('delete', $anderer));
    }

    public function test_mitarbeiter_sieht_kunden_nur_ueber_eigene_projekte(): void
    {
        $mitarbeiter = $this->mitarbeiter();

        $meiner = Customer::factory()->create();
        $fremder = Customer::factory()->create();

        $projekt = Project::factory()->for($meiner, 'customer')->create();
        $projekt->mitarbeiter()->attach($mitarbeiter);
        Project::factory()->for($fremder, 'customer')->create();

        $this->assertTrue($mitarbeiter->can('view', $meiner));
        $this->assertFalse($mitarbeiter->can('view', $fremder));
    }

    public function test_zeitbuchungen_gehoeren_ihrem_urheber(): void
    {
        $eins = $this->mitarbeiter();
        $zwei = $this->mitarbeiter();

        $eintrag = TimeEntry::factory()->for($eins, 'user')->create();

        $this->assertTrue($eins->can('update', $eintrag));
        $this->assertFalse($zwei->can('update', $eintrag));
        $this->assertTrue($this->admin()->can('update', $eintrag));
    }

    public function test_kommentare_bearbeitet_nur_der_autor(): void
    {
        $autor = $this->mitarbeiter();
        $anderer = $this->mitarbeiter();

        $kommentar = Comment::factory()->for($autor, 'autor')->create();

        $this->assertTrue($autor->can('update', $kommentar));
        $this->assertFalse($anderer->can('update', $kommentar));
    }

    public function test_mitarbeiter_wird_von_der_nutzerverwaltung_abgewiesen(): void
    {
        // Der Menüpunkt ist für ihn ohnehin unsichtbar — entscheidend ist,
        // dass der direkte Aufruf der URL abgewiesen wird. Ein verstecktes
        // Menü ist keine Zugangskontrolle.
        $this->actingAs($this->mitarbeiter())
            ->get('/users')
            ->assertForbidden();
    }

    public function test_admin_erreicht_die_nutzerverwaltung(): void
    {
        $this->actingAs($this->admin())
            ->get('/users')
            ->assertOk();
    }

    public function test_stadium_mit_tickets_laesst_sich_nicht_loeschen(): void
    {
        $admin = $this->admin();
        $status = TicketStatus::factory()->create();

        $this->assertTrue($admin->can('delete', $status));

        Ticket::factory()->create(['ticket_status_id' => $status->id]);

        $this->assertFalse($admin->can('delete', $status->fresh()));
    }
}
