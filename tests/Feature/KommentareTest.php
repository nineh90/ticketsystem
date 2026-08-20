<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\CommentsRelationManager;
use App\Models\Comment;
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
 * Der Gesprächsfaden am Ticket.
 *
 * Zwei Dinge waren daran falsch, und beide fielen erst im Gebrauch auf:
 *
 *  - **Lange Kommentare endeten nach 300 Zeichen mit "…"** und ließen sich
 *    nirgends zu Ende lesen. Ein abgeschnittener Kommentar ist kein kürzerer,
 *    sondern ein verlorener — und ausgerechnet der lange enthält das, worauf
 *    es ankommt.
 *  - **Ein Administrator konnte den Kommentar eines Kunden bearbeiten.** Was
 *    der Kunde geschrieben hat, ist seine Aussage; wer sie ändern kann, macht
 *    den ganzen Verlauf als Beleg wertlos, im Streitfall auch gegen uns.
 */
class KommentareTest extends TestCase
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

    private function kundenzugang(Customer $kunde): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
        ]);
    }

    private function ticket(?Customer $kunde = null): Ticket
    {
        $kunde ??= Customer::factory()->create();

        return Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->for(TicketStatus::factory()->create(), 'status')
            ->create(['customer_id' => $kunde->getKey()]);
    }

    // --------------------------------------------------------- Lesen

    public function test_ein_langer_kommentar_steht_vollstaendig_da(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket();

        // Deutlich über der alten Grenze von 300 Zeichen, und das Ende ist
        // eindeutig wiederzuerkennen.
        $text = str_repeat('Der Kunde beschreibt den Fehler ausführlich. ', 20).'UND DAS STEHT GANz AM ENDE.';

        Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $admin->getKey(),
            'body' => $text,
            'ist_intern' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CommentsRelationManager::class, [
                'ownerRecord' => $ticket,
                'pageClass' => ViewTicket::class,
            ])
            ->assertSuccessful()
            ->assertSee('UND DAS STEHT GANz AM ENDE.');
    }

    // ----------------------------------------------------------- Ändern

    public function test_der_kommentar_eines_kunden_laesst_sich_nicht_bearbeiten(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $ticket = $this->ticket($kunde);

        $kommentar = Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $this->kundenzugang($kunde)->getKey(),
            'body' => 'Das war so nicht abgesprochen.',
            'ist_intern' => false,
        ]);

        $this->assertFalse($admin->can('update', $kommentar));

        Livewire::actingAs($admin)
            ->test(CommentsRelationManager::class, [
                'ownerRecord' => $ticket,
                'pageClass' => ViewTicket::class,
            ])
            ->assertActionHidden(TestAction::make('edit')->table($kommentar));
    }

    public function test_auch_der_admin_bearbeitet_fremde_kommentare_nicht(): void
    {
        $admin = $this->admin();
        $kollege = $this->mitarbeiter();

        $kommentar = Comment::factory()->for($kollege, 'autor')->create();

        $this->assertFalse($admin->can('update', $kommentar));
        $this->assertTrue($kollege->can('update', $kommentar));
    }

    // ----------------------------------------------------------- Löschen

    public function test_unangemessenes_darf_der_admin_loeschen(): void
    {
        // Die Ausnahme mit Ansage: löschen ist ehrlicher als ändern — danach
        // steht dort nichts, statt eines Satzes, den der Urheber nie
        // geschrieben hat.
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $ticket = $this->ticket($kunde);

        $kommentar = Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $this->kundenzugang($kunde)->getKey(),
            'body' => 'Unangemessenes.',
            'ist_intern' => false,
        ]);

        $this->assertTrue($admin->can('delete', $kommentar));

        Livewire::actingAs($admin)
            ->test(CommentsRelationManager::class, [
                'ownerRecord' => $ticket,
                'pageClass' => ViewTicket::class,
            ])
            ->assertActionVisible(TestAction::make('delete')->table($kommentar));
    }

    public function test_ein_mitarbeiter_loescht_fremde_kommentare_nicht(): void
    {
        $kollege = $this->mitarbeiter();

        $kommentar = Comment::factory()->for($this->mitarbeiter(), 'autor')->create();

        $this->assertFalse($kollege->can('delete', $kommentar));
    }

    public function test_kunden_aendern_nichts_nachtraeglich(): void
    {
        // Auch nicht den eigenen Beitrag: für ihn ist der Verlauf das, worauf
        // er sich beruft, und dieselbe Überlegung gilt in beide Richtungen.
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $kommentar = Comment::factory()->for($zugang, 'autor')->create();

        $this->assertFalse($zugang->can('update', $kommentar));
        $this->assertFalse($zugang->can('delete', $kommentar));
    }
}
