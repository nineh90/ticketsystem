<?php

namespace Tests\Feature;

use App\Enums\DokumentArt;
use App\Enums\Rolle;
use App\Filament\Pages\Abrechnung as AbrechnungsSeite;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\DokumenteRelationManager;
use App\Models\Customer;
use App\Models\Dokument;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Abrechnung;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Offene abrechenbare Zeit.
 *
 * Der Schwerpunkt liegt darauf, was *nicht* mitzählt. Eine zu hohe Zahl auf
 * dieser Seite führt zu einer Rechnung über Arbeit, die niemand berechnen
 * wollte — und das merkt man erst, wenn der Kunde anruft.
 */
class AbrechnungTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    /** Ein Ticket dieses Kunden, an dem Zeit hängen kann. */
    private function ticketFuer(Customer $kunde): Ticket
    {
        return Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create(['ticket_status_id' => TicketStatus::factory()->create()->id]);
    }

    public function test_offene_zeit_wird_je_kunde_summiert(): void
    {
        $admin = $this->admin();

        $viel = Customer::factory()->create(['name' => 'Vielarbeiter']);
        $wenig = Customer::factory()->create(['name' => 'Wenigarbeiter']);

        $ticketA = $this->ticketFuer($viel);
        TimeEntry::factory()->create(['ticket_id' => $ticketA->id, 'minuten' => 90]);
        TimeEntry::factory()->create(['ticket_id' => $ticketA->id, 'minuten' => 30]);

        TimeEntry::factory()->create([
            'ticket_id' => $this->ticketFuer($wenig)->id,
            'minuten' => 45,
        ]);

        $zeilen = Abrechnung::jeKunde($admin);

        $this->assertCount(2, $zeilen);
        $this->assertSame('Vielarbeiter', $zeilen->first()->kunde->name);
        $this->assertSame(120, $zeilen->first()->minuten);
        $this->assertSame(2, $zeilen->first()->buchungen);
    }

    public function test_nicht_abrechenbare_zeit_zaehlt_nicht(): void
    {
        // Kulanz und Einarbeitung stehen mit demselben Recht in den Zeiten —
        // sie gehören nur nicht auf die Rechnung.
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $ticket = $this->ticketFuer($kunde);

        TimeEntry::factory()->create(['ticket_id' => $ticket->id, 'minuten' => 60]);
        TimeEntry::factory()->create([
            'ticket_id' => $ticket->id,
            'minuten' => 600,
            'abrechenbar' => false,
        ]);

        $this->assertSame(60, Abrechnung::minutenFuer($kunde, $admin));
    }

    public function test_eine_laufende_uhr_zaehlt_nicht(): void
    {
        // Sie steht mit 0 Minuten in der Spalte. Mitgezählt wäre sie danach
        // als abgerechnet markiert, ohne dass je etwas dafür berechnet wurde.
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $ticket = $this->ticketFuer($kunde);

        TimeEntry::factory()->laufend()->create(['ticket_id' => $ticket->id]);

        $this->assertSame(0, Abrechnung::minutenFuer($kunde, $admin));
        $this->assertTrue(Abrechnung::jeKunde($admin)->isEmpty());
    }

    public function test_zugeordnete_zeit_faellt_aus_der_liste(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $ticket = $this->ticketFuer($kunde);

        $offen = TimeEntry::factory()->create(['ticket_id' => $ticket->id, 'minuten' => 60]);
        $abgerechnet = TimeEntry::factory()->create(['ticket_id' => $ticket->id, 'minuten' => 120]);

        $rechnung = Dokument::factory()->for($kunde, 'customer')->create();
        $abgerechnet->forceFill(['dokument_id' => $rechnung->getKey()])->save();

        $this->assertSame(60, Abrechnung::minutenFuer($kunde, $admin));
        $this->assertSame(120, $rechnung->zugeordneteMinuten());

        // Gegenprobe: die offene ist wirklich noch dabei.
        $ids = Abrechnung::buchungenFuer($kunde, $admin)->pluck('id');
        $this->assertTrue($ids->contains($offen->id));
        $this->assertFalse($ids->contains($abgerechnet->id));
    }

    public function test_eine_geloeschte_rechnung_gibt_die_zeiten_wieder_frei(): void
    {
        // Sie sind dann tatsächlich nicht mehr abgerechnet — alles andere
        // wäre Geld, das lautlos verschwindet.
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $zeit = TimeEntry::factory()->create([
            'ticket_id' => $this->ticketFuer($kunde)->id,
            'minuten' => 75,
        ]);

        $rechnung = Dokument::factory()->for($kunde, 'customer')->create();
        $zeit->forceFill(['dokument_id' => $rechnung->getKey()])->save();
        $this->assertSame(0, Abrechnung::minutenFuer($kunde, $admin));

        $rechnung->delete();

        $this->assertNull($zeit->fresh()->dokument_id);
        $this->assertSame(75, Abrechnung::minutenFuer($kunde, $admin));
    }

    public function test_mitarbeiter_sieht_nur_seine_kunden(): void
    {
        $mitarbeiter = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);

        $meiner = Customer::factory()->create(['name' => 'Mein Kunde']);
        $projekt = Project::factory()->for($meiner, 'customer')->create();
        $projekt->mitarbeiter()->attach($mitarbeiter);
        $status = TicketStatus::factory()->create();

        $meinTicket = Ticket::factory()->for($projekt, 'project')->create(['ticket_status_id' => $status->id]);
        TimeEntry::factory()->create(['ticket_id' => $meinTicket->id, 'minuten' => 60]);

        $fremder = Customer::factory()->create(['name' => 'Fremder Kunde']);
        TimeEntry::factory()->create([
            'ticket_id' => $this->ticketFuer($fremder)->id,
            'minuten' => 600,
        ]);

        $namen = Abrechnung::jeKunde($mitarbeiter)->pluck('kunde.name');

        $this->assertTrue($namen->contains('Mein Kunde'));
        $this->assertFalse($namen->contains('Fremder Kunde'));
    }

    public function test_die_seite_zeigt_die_offene_zeit(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create(['name' => 'Beispiel GmbH']);

        TimeEntry::factory()->create([
            'ticket_id' => $this->ticketFuer($kunde)->id,
            'minuten' => 135,
        ]);

        Livewire::actingAs($admin)
            ->test(AbrechnungsSeite::class)
            ->assertSuccessful()
            ->assertSee('Beispiel GmbH')
            // 135 Minuten sind 2:15 h.
            ->assertSee('2:15 h');
    }

    public function test_die_seite_sagt_es_wenn_nichts_offen_ist(): void
    {
        // "Nichts offen" kann auch heißen, dass jemand alles als nicht
        // abrechenbar gebucht hat — der Hinweis darauf gehört dazu.
        Livewire::actingAs($this->admin())
            ->test(AbrechnungsSeite::class)
            ->assertSuccessful()
            ->assertSee('Nichts offen')
            ->assertSee('abrechenbar');
    }

    public function test_eine_buchung_ueber_null_minuten_taucht_nicht_auf(): void
    {
        // An echten Daten aufgefallen: eine sofort wieder gestoppte Uhr. Sie
        // lässt sich nicht abrechnen und stünde sonst für immer in der
        // Auswahl.
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $ticket = $this->ticketFuer($kunde);

        TimeEntry::factory()->create(['ticket_id' => $ticket->id, 'minuten' => 0]);
        $echt = TimeEntry::factory()->create(['ticket_id' => $ticket->id, 'minuten' => 45]);

        $ids = Abrechnung::buchungenFuer($kunde, $admin)->pluck('id');

        $this->assertSame([$echt->id], $ids->all());
        $this->assertSame(45, Abrechnung::minutenFuer($kunde, $admin));
    }

    public function test_die_aktion_ordnet_zu_und_loest_wieder(): void
    {
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $ticket = $this->ticketFuer($kunde);

        $eine = TimeEntry::factory()->create(['ticket_id' => $ticket->id, 'minuten' => 60]);
        $andere = TimeEntry::factory()->create(['ticket_id' => $ticket->id, 'minuten' => 30]);

        $rechnung = Dokument::factory()->for($kunde, 'customer')->create();

        $tabelle = fn () => Livewire::actingAs($admin)->test(DokumenteRelationManager::class, [
            'ownerRecord' => $kunde,
            'pageClass' => ViewCustomer::class,
        ]);

        $tabelle()
            ->callAction(
                TestAction::make('zeiten')->table($rechnung),
                ['zeiten' => [$eine->id, $andere->id]],
            )
            ->assertHasNoActionErrors();

        $this->assertSame($rechnung->getKey(), $eine->fresh()->dokument_id);
        $this->assertSame(90, $rechnung->zugeordneteMinuten());
        $this->assertSame(0, Abrechnung::minutenFuer($kunde, $admin));

        // Abwählen muss die Zuordnung wieder lösen — sonst bliebe ein
        // Fehlgriff für immer stehen.
        $tabelle()
            ->callAction(
                TestAction::make('zeiten')->table($rechnung),
                ['zeiten' => [$eine->id]],
            )
            ->assertHasNoActionErrors();

        $this->assertNull($andere->fresh()->dokument_id);
        $this->assertSame(60, $rechnung->fresh()->zugeordneteMinuten());
        $this->assertSame(30, Abrechnung::minutenFuer($kunde, $admin));
    }

    public function test_fremde_buchungen_lassen_sich_nicht_anhaengen(): void
    {
        // Die Auswahl ist doppelt begrenzt — auf den Kunden und auf das, was
        // der Nutzer sehen darf. Eine nachgebaute Anfrage kommt nicht durch.
        $admin = $this->admin();
        $kunde = Customer::factory()->create();
        $fremder = Customer::factory()->create();

        $fremdeZeit = TimeEntry::factory()->create([
            'ticket_id' => $this->ticketFuer($fremder)->id,
            'minuten' => 90,
        ]);

        $rechnung = Dokument::factory()->for($kunde, 'customer')->create();

        Livewire::actingAs($admin)
            ->test(DokumenteRelationManager::class, [
                'ownerRecord' => $kunde,
                'pageClass' => ViewCustomer::class,
            ])
            ->callAction(
                TestAction::make('zeiten')->table($rechnung),
                ['zeiten' => [$fremdeZeit->id]],
            );

        $this->assertNull($fremdeZeit->fresh()->dokument_id);
    }

    public function test_nur_rechnungen_bekommen_zeiten(): void
    {
        // Ein Angebot deckt keine geleistete Arbeit ab, es kündigt sie an.
        $this->assertSame(DokumentArt::Rechnung, DokumentArt::from('rechnung'));

        $kunde = Customer::factory()->create();
        $angebot = Dokument::factory()->for($kunde, 'customer')->angebot()->create();
        $rechnung = Dokument::factory()->for($kunde, 'customer')->create();

        $this->assertNotSame(DokumentArt::Rechnung, $angebot->art);
        $this->assertSame(DokumentArt::Rechnung, $rechnung->art);
    }
}
