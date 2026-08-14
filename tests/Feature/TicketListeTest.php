<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sichtbarkeit an der Tabelle selbst.
 *
 * BerechtigungenTest prüft die Policies, also den Direktaufruf. Hier geht es
 * um die andere Hälfte: was überhaupt in der Liste erscheint. Beides ist
 * nötig — eine Policy allein ließe fremde Tickets in Tabellen und Zählern
 * stehen, auch wenn man sie nicht öffnen kann.
 */
class TicketListeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitarbeiter_sieht_in_der_liste_nur_eigene_projekte(): void
    {
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $status = TicketStatus::factory()->create();

        $meins = Project::factory()->create();
        $meins->mitarbeiter()->attach($mitarbeiter);

        $eigenes = Ticket::factory()->for($meins, 'project')
            ->create(['ticket_status_id' => $status->id]);
        $fremdes = Ticket::factory()
            ->create(['ticket_status_id' => $status->id]);

        Livewire::actingAs($mitarbeiter)
            ->test(ListTickets::class)
            ->assertCanSeeTableRecords([$eigenes])
            ->assertCanNotSeeTableRecords([$fremdes]);
    }

    public function test_admin_sieht_alle_tickets(): void
    {
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        $status = TicketStatus::factory()->create();
        $tickets = Ticket::factory()->count(3)
            ->create(['ticket_status_id' => $status->id]);

        Livewire::actingAs($admin)
            ->test(ListTickets::class)
            ->assertCanSeeTableRecords($tickets);
    }

    public function test_erledigte_tickets_sind_standardmaessig_ausgeblendet(): void
    {
        // "Offen" ist der erste Reiter und damit der voreingestellte —
        // erledigte Tickets sammeln sich an und machen die Liste sonst binnen
        // Wochen unbrauchbar.
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        $offen = TicketStatus::factory()->create();
        $fertig = TicketStatus::factory()->abschluss()->create();

        $laufend = Ticket::factory()->create(['ticket_status_id' => $offen->id]);
        $erledigt = Ticket::factory()->create(['ticket_status_id' => $fertig->id]);

        Livewire::actingAs($admin)
            ->test(ListTickets::class)
            ->assertCanSeeTableRecords([$laufend])
            ->assertCanNotSeeTableRecords([$erledigt]);
    }

    public function test_reiter_meine_zeigt_nur_die_eigenen_tickets(): void
    {
        // Der Handgriff, um den es Kevin ging: ein Klick oben, und die Liste
        // zeigt nur, was ihm zugewiesen ist.
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $offen = TicketStatus::factory()->create();
        $projekt = Project::factory()->create();
        $projekt->mitarbeiter()->attach($mitarbeiter);

        $meins = Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $offen->id,
            'assigned_to' => $mitarbeiter->id,
        ]);
        // Aus demselben Projekt, aber jemand anderem zugewiesen: im Reiter
        // "Offen" sichtbar, unter "Meine" nicht.
        $vomKollegen = Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $offen->id,
        ]);

        Livewire::actingAs($mitarbeiter)
            ->test(ListTickets::class)
            ->assertCanSeeTableRecords([$meins, $vomKollegen])
            ->set('activeTab', 'meine')
            ->assertCanSeeTableRecords([$meins])
            ->assertCanNotSeeTableRecords([$vomKollegen]);
    }

    public function test_reiter_erledigt_holt_abgeschlossene_tickets_hervor(): void
    {
        // Vorher gab es dafür keinen Weg: der Schalter "Nur offene" war
        // voreingestellt und musste erst im Filtermenü gefunden werden.
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        $offen = TicketStatus::factory()->create();
        $fertig = TicketStatus::factory()->abschluss()->create();

        $laufend = Ticket::factory()->create(['ticket_status_id' => $offen->id]);
        $erledigt = Ticket::factory()->create(['ticket_status_id' => $fertig->id]);

        Livewire::actingAs($admin)
            ->test(ListTickets::class)
            ->set('activeTab', 'erledigt')
            ->assertCanSeeTableRecords([$erledigt])
            ->assertCanNotSeeTableRecords([$laufend]);
    }

    public function test_reiter_ueberfaellig_laesst_erledigtes_aussen_vor(): void
    {
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        $offen = TicketStatus::factory()->create();
        $fertig = TicketStatus::factory()->abschluss()->create();

        $ueberfaellig = Ticket::factory()->create([
            'ticket_status_id' => $offen->id,
            'faellig_am' => today()->subDays(2),
        ]);
        // Termin ebenfalls in der Vergangenheit, aber fertig — das ist nicht
        // überfällig, sondern erledigt.
        $spaetErledigt = Ticket::factory()->create([
            'ticket_status_id' => $fertig->id,
            'faellig_am' => today()->subDays(2),
        ]);

        Livewire::actingAs($admin)
            ->test(ListTickets::class)
            ->set('activeTab', 'ueberfaellig')
            ->assertCanSeeTableRecords([$ueberfaellig])
            ->assertCanNotSeeTableRecords([$spaetErledigt]);
    }

    public function test_navigationsbadge_zaehlt_nur_sichtbare_offene_tickets(): void
    {
        $mitarbeiter = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $offen = TicketStatus::factory()->create();
        $fertig = TicketStatus::factory()->abschluss()->create();

        $meins = Project::factory()->create();
        $meins->mitarbeiter()->attach($mitarbeiter);

        Ticket::factory()->count(2)->for($meins, 'project')
            ->create(['ticket_status_id' => $offen->id]);
        Ticket::factory()->for($meins, 'project')
            ->create(['ticket_status_id' => $fertig->id]);
        // Fremdes offenes Ticket — darf nicht mitgezählt werden.
        Ticket::factory()->create(['ticket_status_id' => $offen->id]);

        $this->actingAs($mitarbeiter);

        $this->assertSame(
            '2',
            TicketResource::getNavigationBadge(),
        );
    }
}
