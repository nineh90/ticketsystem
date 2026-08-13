<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Widgets\MeinUeberblick;
use App\Models\Project;
use App\Models\User;
use App\Support\Sichtbarkeit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Der Fall, der beim ersten Mitarbeiter im Live-System auffiel.
 *
 * Kevin konnte sich anmelden, sah aber nirgends etwas — richtig so, denn er
 * war keinem Projekt zugeordnet. Nur sagte ihm das niemand: die Listen
 * meldeten "keine Tickets, oder die Filter sind zu eng", was ihn auf die
 * Suche nach einem Filter schickte, der gar nicht das Problem war.
 */
class OhneProjektzuordnungTest extends TestCase
{
    use RefreshDatabase;

    private function mitarbeiter(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);
    }

    public function test_mitarbeiter_ohne_zuordnung_wird_erkannt(): void
    {
        $this->actingAs($this->mitarbeiter());

        $this->assertTrue(Sichtbarkeit::ohneProjekte());
        $this->assertNotNull(Sichtbarkeit::hinweis());
    }

    public function test_mit_zuordnung_kein_hinweis(): void
    {
        $nutzer = $this->mitarbeiter();
        Project::factory()->create()->mitarbeiter()->attach($nutzer);

        $this->actingAs($nutzer);

        $this->assertFalse(Sichtbarkeit::ohneProjekte());
        $this->assertNull(Sichtbarkeit::hinweis());
    }

    public function test_admin_bekommt_nie_den_hinweis(): void
    {
        // Admins sehen alles, auch ohne Zuordnung — für sie wäre der Hinweis
        // schlicht falsch.
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        $this->actingAs($admin);

        $this->assertFalse(Sichtbarkeit::ohneProjekte());
    }

    public function test_dashboard_erklaert_die_leere(): void
    {
        Livewire::actingAs($this->mitarbeiter())
            ->test(MeinUeberblick::class)
            ->assertSuccessful()
            ->assertSee('Kein Projekt zugeordnet')
            ->assertSee('Verwaltung');
    }

    public function test_kanban_erklaert_die_leere(): void
    {
        $this->actingAs($this->mitarbeiter())
            ->get('/kanban')
            ->assertOk()
            ->assertSee('kein Projekt zugeordnet', escape: false);
    }

    public function test_ticketliste_erklaert_die_leere(): void
    {
        $this->actingAs($this->mitarbeiter())
            ->get('/tickets')
            ->assertOk()
            ->assertSee('Kein Projekt zugeordnet');
    }
}
