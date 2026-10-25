<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Eine halbe Stunde dran gesessen" — buchen ohne Von und Bis (NID-34).
 */
class ZeitBuchenTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(): Ticket
    {
        $kunde = Customer::factory()->create();

        return Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create([
                'customer_id' => $kunde->getKey(),
                'ticket_status_id' => TicketStatus::factory()->create()->getKey(),
            ]);
    }

    private function tabelle(User $nutzer, Ticket $ticket)
    {
        return Livewire::actingAs($nutzer)->test(TimeEntriesRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => ViewTicket::class,
        ]);
    }

    public function test_eine_dauer_wird_ab_jetzt_gebucht(): void
    {
        $this->travelTo(now()->setTime(14, 7, 33));

        $nutzer = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $ticket = $this->ticket();

        $this->tabelle($nutzer, $ticket)
            ->callAction(TestAction::make('buchen')->table(), [
                'minuten' => 30,
                'beschreibung' => 'Texte eingepflegt',
            ])
            ->assertHasNoActionErrors();

        $zeit = $ticket->timeEntries()->sole();

        $this->assertSame(30, $zeit->minuten);
        $this->assertSame($nutzer->getKey(), $zeit->user_id);
        $this->assertSame('14:07', $zeit->gestartet_am->format('H:i'));
        $this->assertSame('14:37', $zeit->beendet_am->format('H:i'));
        $this->assertFalse($zeit->laeuft());
        $this->assertTrue($zeit->abrechenbar);
        $this->assertSame('Texte eingepflegt', $zeit->beschreibung);
    }

    public function test_ein_knopf_setzt_die_minuten(): void
    {
        $nutzer = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $ticket = $this->ticket();

        $this->tabelle($nutzer, $ticket)
            ->mountAction(TestAction::make('buchen')->table())
            ->fillForm(['vorschlag' => 90])
            ->assertSchemaStateSet(['minuten' => 90])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(90, $ticket->timeEntries()->sole()->minuten);
    }

    public function test_ohne_dauer_wird_nichts_gebucht(): void
    {
        $nutzer = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
        $ticket = $this->ticket();

        $this->tabelle($nutzer, $ticket)
            ->callAction(TestAction::make('buchen')->table(), ['minuten' => 0])
            ->assertHasActionErrors(['minuten']);

        $this->assertSame(0, $ticket->timeEntries()->count());
    }
}
