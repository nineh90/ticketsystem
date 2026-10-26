<?php

namespace Tests\Feature;

use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Filament\Kunde\Resources\Anliegen\Pages\ViewAnliegen;
use App\Filament\Kunde\Resources\Anliegen\RelationManagers\AntwortenRelationManager;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Livewire\Ticketverlauf;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Absätze bleiben Absätze (NID-31).
 *
 * Kunden schicken Texte für ihre Website als Anliegen. Liefe der zu einem
 * Block zusammen, müsste jemand die Absätze aus dem Gedächtnis rekonstruieren.
 * Gespeichert waren die Zeilenumbrüche immer — es ging nur um die Anzeige.
 */
class AbsaetzeTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT = "Erster Absatz über uns.\n\nZweiter Absatz mit den Öffnungszeiten.";

    private function ticket(Customer $kunde): Ticket
    {
        $ticket = Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create([
                'customer_id' => $kunde->getKey(),
                'ticket_status_id' => TicketStatus::factory()->create()->getKey(),
                'quelle' => Quelle::Kunde,
                'beschreibung' => self::TEXT,
            ]);

        $ticket->comments()->create([
            'user_id' => User::factory()->create(['rolle' => Rolle::Admin])->getKey(),
            'body' => self::TEXT,
            'ist_intern' => false,
        ]);

        return $ticket;
    }

    public function test_drinnen_bleiben_die_absaetze_stehen(): void
    {
        $ticket = $this->ticket(Customer::factory()->create());

        $this->actingAs(User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]));
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSeeHtml('whitespace-pre-line')
            ->assertSeeText('Zweiter Absatz');

        Livewire::test(Ticketverlauf::class, ['ticket' => $ticket])
            ->assertSeeHtml('whitespace-pre-line');
    }

    public function test_beim_kunden_bleiben_die_absaetze_stehen(): void
    {
        $kunde = Customer::factory()->create();
        $ticket = $this->ticket($kunde);

        $this->actingAs(User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
        ]), 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(ViewAnliegen::class, ['record' => $ticket->getKey()])
            ->assertSeeHtml('whitespace-pre-line');

        Livewire::test(AntwortenRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => ViewAnliegen::class,
        ])->assertSeeHtml('whitespace-pre-line');
    }
}
