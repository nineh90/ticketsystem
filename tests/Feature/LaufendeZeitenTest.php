<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Widgets\WerArbeitetGerade;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\LaufendeZeiten;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die laufenden Uhren — auf dem Dashboard und über der Zeitentabelle.
 *
 * Der Anlass ist eine vergessene Uhr, die über Nacht weiterlief. Getestet
 * wird deshalb vor allem, was daran schiefgehen kann: dass eine laufende
 * Buchung im Widget fehlt, dass eine fremde dort auftaucht, obwohl man das
 * Ticket gar nicht sehen darf, und dass der Stopp-Knopf mehr stoppt, als er
 * darf.
 */
class LaufendeZeitenTest extends TestCase
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

    /** Ein Ticket, das dem Mitarbeiter über seinen Kunden gehört. */
    private function ticketFuer(?User $mitarbeiter = null): Ticket
    {
        $kunde = Customer::factory()->create();

        if ($mitarbeiter) {
            $kunde->mitarbeiter()->attach($mitarbeiter);
        }

        return Ticket::factory()
            ->for(TicketStatus::factory()->create(), 'status')
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create(['customer_id' => $kunde->id]);
    }

    private function uhrLaeuft(User $wer, Ticket $ticket, int $seitMinuten = 30): TimeEntry
    {
        return TimeEntry::create([
            'ticket_id' => $ticket->id,
            'user_id' => $wer->id,
            'gestartet_am' => now()->subMinutes($seitMinuten),
        ]);
    }

    public function test_widget_zeigt_die_laufende_uhr_eines_kollegen(): void
    {
        $admin = $this->admin();
        $kollege = $this->mitarbeiter();

        $this->uhrLaeuft($kollege, $this->ticketFuer());

        Livewire::actingAs($admin)
            ->test(WerArbeitetGerade::class)
            ->assertSuccessful()
            ->assertSee($kollege->name)
            ->assertSee('0:30 h');
    }

    public function test_widget_verschwindet_wenn_keine_uhr_laeuft(): void
    {
        // Ohne das stünde jeden Tag eine leere Karte an der besten Stelle des
        // Dashboards — und die liest nach einer Woche niemand mehr.
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->assertFalse(WerArbeitetGerade::canView());

        $this->uhrLaeuft($admin, $this->ticketFuer());

        $this->assertTrue(WerArbeitetGerade::canView());
    }

    public function test_beendete_buchungen_stehen_nicht_in_der_liste(): void
    {
        $admin = $this->admin();

        TimeEntry::factory()->create(['minuten' => 42]);

        $this->assertCount(0, LaufendeZeiten::fuer($admin));
    }

    public function test_mitarbeiter_sieht_fremde_uhren_nur_aus_eigenen_projekten(): void
    {
        $nutzer = $this->mitarbeiter();
        $kollege = $this->mitarbeiter();

        $meins = $this->ticketFuer($nutzer);
        $fremd = $this->ticketFuer();

        $sichtbar = $this->uhrLaeuft($kollege, $meins);
        $this->uhrLaeuft($kollege, $fremd);

        $ids = LaufendeZeiten::fuer($nutzer)->modelKeys();

        $this->assertSame([$sichtbar->id], $ids);
    }

    public function test_eigene_uhr_bleibt_sichtbar_ohne_zuordnung(): void
    {
        // Genau die wäre sonst die, die niemand mehr stoppt: wem das Projekt
        // entzogen wurde, dem verschwände die eigene laufende Buchung.
        $nutzer = $this->mitarbeiter();

        $eigene = $this->uhrLaeuft($nutzer, $this->ticketFuer());

        $this->assertSame([$eigene->id], LaufendeZeiten::fuer($nutzer)->modelKeys());
    }

    public function test_kunde_sieht_keine_zeiten(): void
    {
        $kunde = Customer::factory()->create();

        $zugang = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'customer_id' => $kunde->id,
            'panel_zugang' => false,
        ]);

        $this->uhrLaeuft($this->admin(), $this->ticketFuer());

        $this->assertCount(0, LaufendeZeiten::fuer($zugang));
        $this->assertFalse(LaufendeZeiten::gibtEs($zugang));
    }

    public function test_stoppen_aus_dem_widget_beendet_die_buchung(): void
    {
        $nutzer = $this->mitarbeiter();
        $uhr = $this->uhrLaeuft($nutzer, $this->ticketFuer($nutzer), seitMinuten: 75);

        Livewire::actingAs($nutzer)
            ->test(WerArbeitetGerade::class)
            ->call('zeitStoppen', $uhr->id)
            ->assertSuccessful();

        $uhr->refresh();

        $this->assertFalse($uhr->laeuft());
        $this->assertSame(75, $uhr->minuten);
    }

    public function test_mitarbeiter_kann_fremde_uhr_nicht_stoppen(): void
    {
        $nutzer = $this->mitarbeiter();
        $kollege = $this->mitarbeiter();

        $uhr = $this->uhrLaeuft($kollege, $this->ticketFuer($nutzer));

        Livewire::actingAs($nutzer)
            ->test(WerArbeitetGerade::class)
            ->call('zeitStoppen', $uhr->id);

        $this->assertTrue($uhr->fresh()->laeuft());
    }

    public function test_admin_kann_die_vergessene_uhr_eines_anderen_stoppen(): void
    {
        // Der eigentliche Zweck der Liste: die vergessene Uhr sieht meistens
        // jemand anderes zuerst.
        $admin = $this->admin();
        $kollege = $this->mitarbeiter();

        $uhr = $this->uhrLaeuft($kollege, $this->ticketFuer(), seitMinuten: 14 * 60);

        Livewire::actingAs($admin)
            ->test(WerArbeitetGerade::class)
            ->call('zeitStoppen', $uhr->id);

        $this->assertFalse($uhr->fresh()->laeuft());
    }

    public function test_lange_laufende_uhr_faellt_auf(): void
    {
        $nutzer = $this->mitarbeiter();
        $ticket = $this->ticketFuer($nutzer);

        $frisch = $this->uhrLaeuft($nutzer, $ticket, seitMinuten: 30);
        $this->assertFalse($frisch->laeuftAuffaelligLange());

        $lang = $this->uhrLaeuft($nutzer, $ticket, seitMinuten: 9 * 60);
        $this->assertTrue($lang->laeuftAuffaelligLange());

        // Von gestern — auch wenn es erst zwei Stunden sind, hat sie niemand
        // über Nacht gestoppt.
        $ueberNacht = TimeEntry::create([
            'ticket_id' => $ticket->id,
            'user_id' => $nutzer->id,
            'gestartet_am' => now()->subDay()->setTime(23, 0),
        ]);

        $this->assertTrue($ueberNacht->laeuftAuffaelligLange());
    }

    public function test_beendete_buchung_faellt_nie_auf(): void
    {
        $eintrag = TimeEntry::factory()->create(['minuten' => 20 * 60]);

        $this->assertFalse($eintrag->laeuftAuffaelligLange());
    }

    /** Die Zeitentabelle eines Tickets, als Livewire-Komponente. */
    private function zeitentabelle(User $nutzer, Ticket $ticket): Testable
    {
        return Livewire::actingAs($nutzer)
            ->test(TimeEntriesRelationManager::class, [
                'ownerRecord' => $ticket,
                'pageClass' => ViewTicket::class,
            ]);
    }

    public function test_starten_beendet_die_uhr_am_anderen_ticket(): void
    {
        // Der Alltagsfall: man wechselt das Ticket und denkt nicht daran, dass
        // die alte Uhr noch läuft. Vorher war der Startknopf dann einfach
        // verschwunden, ohne zu sagen warum.
        $nutzer = $this->mitarbeiter();

        $altes = $this->ticketFuer($nutzer);
        $neues = $this->ticketFuer($nutzer);

        $alte = $this->uhrLaeuft($nutzer, $altes, seitMinuten: 45);

        $this->zeitentabelle($nutzer, $neues)
            ->callAction(TestAction::make('starten')->table())
            ->assertHasNoActionErrors();

        $alte->refresh();

        $this->assertFalse($alte->laeuft(), 'Die alte Uhr läuft noch.');
        $this->assertSame(45, $alte->minuten);

        $neue = $nutzer->fresh()->laufendeZeit();

        $this->assertNotNull($neue);
        $this->assertSame($neues->id, $neue->ticket_id);
    }

    public function test_starten_ohne_laufende_uhr_legt_einfach_los(): void
    {
        $nutzer = $this->mitarbeiter();
        $ticket = $this->ticketFuer($nutzer);

        $this->zeitentabelle($nutzer, $ticket)
            ->callAction(TestAction::make('starten')->table())
            ->assertHasNoActionErrors();

        $this->assertSame($ticket->id, $nutzer->fresh()->laufendeZeit()?->ticket_id);
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_startknopf_fehlt_nur_am_ticket_der_laufenden_uhr(): void
    {
        $nutzer = $this->mitarbeiter();

        $hier = $this->ticketFuer($nutzer);
        $woanders = $this->ticketFuer($nutzer);

        $this->uhrLaeuft($nutzer, $hier);

        // Am selben Ticket wäre "starten" ein Knopf ohne Wirkung …
        $this->zeitentabelle($nutzer, $hier)
            ->assertActionHidden(TestAction::make('starten')->table());

        // … am anderen ist er der Weg zum Wechsel.
        $this->zeitentabelle($nutzer, $woanders)
            ->assertActionVisible(TestAction::make('starten')->table());
    }

    public function test_zeitentabelle_am_ticket_zeigt_die_laufenden_uhren(): void
    {
        $admin = $this->admin();
        $kollege = $this->mitarbeiter();

        $ticket = $this->ticketFuer();
        // Die Uhr hängt bewusst an einem anderen Ticket: dass man sie hier
        // trotzdem sieht, ist der ganze Punkt.
        $this->uhrLaeuft($kollege, $this->ticketFuer());

        Livewire::actingAs($admin)
            ->test(TimeEntriesRelationManager::class, [
                'ownerRecord' => $ticket,
                'pageClass' => ViewTicket::class,
            ])
            ->assertSuccessful()
            ->assertSee('Läuft gerade')
            ->assertSee($kollege->name);
    }
}
