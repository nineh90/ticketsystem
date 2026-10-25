<?php

namespace Tests\Feature;

use App\Enums\ProjektStatus;
use App\Enums\Rolle;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\ProjectsRelationManager;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Projekte loswerden, ohne Geschichte zu verlieren (NID-30).
 *
 * Gelöscht werden darf nur ein leeres Projekt: Tickets hängen mit
 * cascadeOnDelete daran, und mit ihnen die gebuchten Zeiten. Alles andere
 * wird abgesagt oder pausiert und verschwindet beim Kunden aus dem Blick.
 */
class ProjektLoeschenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    private function tabelle(User $nutzer, Customer $kunde)
    {
        return Livewire::actingAs($nutzer)->test(ProjectsRelationManager::class, [
            'ownerRecord' => $kunde,
            'pageClass' => EditCustomer::class,
        ]);
    }

    public function test_ein_leeres_projekt_laesst_sich_beim_kunden_loeschen(): void
    {
        $kunde = Customer::factory()->create();
        $projekt = Project::factory()->for($kunde, 'customer')->create();

        $this->tabelle($this->admin(), $kunde)
            ->callAction(TestAction::make('delete')->table($projekt));

        $this->assertModelMissing($projekt);
    }

    public function test_ein_projekt_mit_tickets_bleibt_stehen(): void
    {
        $kunde = Customer::factory()->create();
        $projekt = Project::factory()->for($kunde, 'customer')->create();
        $ticket = Ticket::factory()->for($projekt, 'project')->create();

        $this->tabelle($this->admin(), $kunde)
            ->assertActionDisabled(TestAction::make('delete')->table($projekt));

        $this->assertFalse($this->admin()->can('delete', $projekt));
        $this->assertModelExists($ticket);
    }

    public function test_nur_admins_loeschen(): void
    {
        $projekt = Project::factory()->create();
        $mitarbeiter = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);

        $this->assertFalse($mitarbeiter->can('delete', $projekt));
    }

    public function test_ruhende_projekte_verschwinden_aus_dem_kundenbereich(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
        ]);

        $aktiv = Project::factory()->for($kunde, 'customer')->create();
        $pausiert = Project::factory()->for($kunde, 'customer')->create(['status' => ProjektStatus::Pausiert]);
        $abgesagt = Project::factory()->for($kunde, 'customer')->create(['status' => ProjektStatus::Abgesagt]);
        $ticket = Ticket::factory()->for($pausiert, 'project')->create();

        $this->assertEquals(
            [$aktiv->getKey()],
            Project::query()->sichtbarFuer($zugang)->pluck('id')->all(),
        );
        $this->assertFalse($zugang->can('view', $pausiert));
        $this->assertFalse($zugang->can('view', $abgesagt));
        $this->assertFalse($zugang->can('view', $ticket));
        $this->assertFalse(Ticket::query()->sichtbarFuer($zugang)->whereKey($ticket->getKey())->exists());

        // Wieder aufgenommen ist es wieder da — der Schalter "für den Kunden
        // sichtbar" wurde dabei nie angefasst.
        $pausiert->update(['status' => ProjektStatus::Aktiv]);
        $this->assertTrue($zugang->can('view', $pausiert->fresh()));
    }

    public function test_pausierte_und_abgesagte_sind_beim_kunden_ausgeblendet(): void
    {
        $kunde = Customer::factory()->create();
        $aktiv = Project::factory()->for($kunde, 'customer')->create();
        $fertig = Project::factory()->for($kunde, 'customer')->create(['status' => ProjektStatus::Abgeschlossen]);
        $pausiert = Project::factory()->for($kunde, 'customer')->create(['status' => ProjektStatus::Pausiert]);
        $abgesagt = Project::factory()->for($kunde, 'customer')->create(['status' => ProjektStatus::Abgesagt]);

        $this->tabelle($this->admin(), $kunde)
            ->assertCanSeeTableRecords([$aktiv, $fertig])
            ->assertCanNotSeeTableRecords([$pausiert, $abgesagt])
            ->filterTable('ruhende_ausblenden', false)
            ->assertCanSeeTableRecords([$aktiv, $fertig, $pausiert, $abgesagt]);
    }
}
