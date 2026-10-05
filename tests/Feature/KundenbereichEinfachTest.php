<?php

namespace Tests\Feature;

use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Filament\Kunde\Pages\Kontakt;
use App\Filament\Kunde\Pages\Nachrichten;
use App\Filament\Kunde\Pages\Zugaenge;
use App\Filament\Kunde\Resources\Projekte\Pages\ViewProjekt;
use App\Filament\Kunde\Resources\Projekte\ProjektResource;
use App\Filament\Kunde\Widgets\EtwasMelden;
use App\Models\Customer;
use App\Models\Nachricht;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\Zugangsdaten;
use App\Support\Unterhaltungen;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Der Kundenbereich, so einfach wie möglich (NID-23).
 *
 * Der Kunde soll vier Dinge können: sehen, wie es steht, etwas melden, uns
 * erreichen und seine Unterlagen finden. Alles, was im Menü darüber
 * hinausgeht, ist eine Tür mehr, vor der er überlegen muss.
 */
class KundenbereichEinfachTest extends TestCase
{
    use RefreshDatabase;

    private function kunde(?Customer $customer = null): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => ($customer ?? Customer::factory()->create())->getKey(),
        ]);
    }

    /** @return Collection<int, string> */
    private function menue(User $kunde): Collection
    {
        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        // Das fertige Menü, wie es gerendert wird — nicht nur die von Hand
        // eingehängten Punkte.
        return collect(Filament::getNavigation())
            ->flatMap(fn ($gruppe) => $gruppe->getItems())
            ->map(fn ($punkt) => $punkt->getLabel())
            ->values();
    }

    public function test_ohne_unterlagen_bleiben_vier_menuepunkte(): void
    {
        $this->assertSame(
            ['Übersicht', 'Anliegen', 'Kontakt', 'Mein Konto'],
            $this->menue($this->kunde())->all(),
        );
    }

    public function test_zugangsdaten_erscheinen_sobald_etwas_freigegeben_ist(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);

        Zugangsdaten::create([
            'customer_id' => $customer->getKey(),
            'bezeichnung' => 'Unser Serverzugang',
            'kunden_sichtbar' => false,
        ]);

        // Was nur wir sehen, macht keinen Menüpunkt auf. Direkt an der Seite
        // gefragt und nicht über das Menü: Filament baut es je Anfrage nur
        // einmal, der zweite Blick weiter unten sähe sonst den alten Stand.
        $this->actingAs($kunde, 'kunde');
        $this->assertFalse(Zugaenge::shouldRegisterNavigation());

        Zugangsdaten::create([
            'customer_id' => $customer->getKey(),
            'bezeichnung' => 'Redaktionszugang',
            'kunden_sichtbar' => true,
        ]);

        $this->assertContains('Zugangsdaten', $this->menue($kunde));
    }

    public function test_die_projektseite_bleibt_ohne_menuepunkt_erreichbar(): void
    {
        // Der Weg führt über die Karte auf der Übersicht.
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create(['kunden_sichtbar' => true]);

        $this->assertNotContains('Projekte', $this->menue($kunde));

        $this->get(ProjektResource::getUrl('view', ['record' => $projekt], panel: 'kunde'))
            ->assertOk();
    }

    public function test_die_projektseite_zeigt_und_zaehlt_unsere_arbeit_nicht(): void
    {
        // Die Lücke, die NID-23 gefunden hat: unter dem Projekt stand jedes
        // Ticket, auch unser Arbeitsbrett.
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create(['kunden_sichtbar' => true]);
        $stand = TicketStatus::factory()->create();

        Ticket::factory()->for($projekt, 'project')->for($stand, 'status')
            ->create(['titel' => 'Hero intern überarbeiten', 'quelle' => Quelle::Manuell]);
        Ticket::factory()->for($projekt, 'project')->for($stand, 'status')
            ->create(['titel' => 'Formular verschickt nichts', 'quelle' => Quelle::Kunde]);

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        $this->assertSame([], ProjektResource::getRelations());

        Livewire::test(ViewProjekt::class, ['record' => $projekt->getRouteKey()])
            ->assertOk()
            ->assertDontSee('Hero intern überarbeiten')
            ->assertSchemaStateSet(['offen' => '1']);
    }

    public function test_auf_der_kontaktseite_laesst_sich_eine_nachricht_schreiben(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(Kontakt::class)
            ->assertSee('Nachricht schreiben')
            ->assertSee('Anliegen anlegen')
            ->set('entwurf', 'Passt Donnerstag um zehn?')
            ->call('senden')
            ->assertSet('entwurf', '');

        $this->assertDatabaseHas('nachrichten', [
            'text' => 'Passt Donnerstag um zehn?',
            'user_id' => $kunde->getKey(),
        ]);
    }

    public function test_unsere_antwort_zaehlt_am_kontakt_und_ist_nach_dem_oeffnen_gelesen(): void
    {
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);

        Nachricht::create([
            'unterhaltung_id' => Unterhaltungen::fuerKunden($customer->getKey())->getKey(),
            'user_id' => $admin->getKey(),
            'text' => 'Donnerstag passt.',
        ]);

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        $this->assertSame('1', Kontakt::getNavigationBadge());

        Livewire::test(Kontakt::class)->assertSee('Donnerstag passt.');

        $this->assertNull(Kontakt::getNavigationBadge());
    }

    public function test_auf_der_uebersicht_laesst_sich_direkt_etwas_melden(): void
    {
        $backlog = TicketStatus::factory()->create(['sortierung' => 1]);
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        $projekt = Project::factory()->for($customer)->create(['kunden_sichtbar' => true]);

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        // Das Formular steht auf der Seite, nicht hinter einem Knopf.
        // Widgets lädt Filament nach, im ersten Abruf steht deshalb nur
        // der Platzhalter mit dem Namen — die Felder prüft der Aufruf darunter.
        $this->get('/kunde')->assertOk()->assertSeeLivewire(EtwasMelden::class);

        Livewire::test(EtwasMelden::class)
            ->assertSee('Etwas melden')
            ->assertSee('Kurz gesagt')
            // Ein einziges Projekt ist vorbelegt — da gibt es nichts zu wählen.
            ->assertSchemaStateSet(['project_id' => $projekt->getKey()])
            ->fillForm(['titel' => 'Das Kontaktformular verschickt nichts'])
            ->call('senden')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $ticket = Ticket::query()->latest('id')->first();
        $this->assertSame('Das Kontaktformular verschickt nichts', $ticket->titel);
        $this->assertSame(Quelle::Kunde, $ticket->quelle);
        $this->assertSame($projekt->getKey(), $ticket->project_id);
        $this->assertSame($backlog->getKey(), $ticket->ticket_status_id);
        $this->assertSame($kunde->getKey(), $ticket->created_by);
    }

    public function test_von_der_uebersicht_aus_geht_nichts_in_ein_fremdes_projekt(): void
    {
        // Dieselbe Schranke wie auf der Seite "Neues Anliegen": die
        // Auswahlliste ist keine Prüfung.
        TicketStatus::factory()->create();
        $customer = Customer::factory()->create();
        $kunde = $this->kunde($customer);
        Project::factory()->for($customer)->create(['kunden_sichtbar' => true]);
        $fremd = Project::factory()->create(['kunden_sichtbar' => true]);

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(EtwasMelden::class)
            ->set('data.project_id', $fremd->getKey())
            ->set('data.titel', 'Darf hier nicht landen')
            ->call('senden')
            ->assertHasErrors();

        $this->assertDatabaseMissing('tickets', ['titel' => 'Darf hier nicht landen']);
    }

    public function test_ohne_freigegebenes_projekt_gibt_es_kein_meldeformular(): void
    {
        $this->actingAs($this->kunde(), 'kunde');

        $this->assertFalse(EtwasMelden::canView());
    }

    public function test_die_alte_nachrichtenadresse_geht_weiter(): void
    {
        // In Glockenmeldungen und Mails steht sie fest drin.
        $kunde = $this->kunde();

        $this->assertNotContains('Nachrichten', $this->menue($kunde));

        $this->get(Nachrichten::getUrl(panel: 'kunde'))->assertOk();
    }
}
