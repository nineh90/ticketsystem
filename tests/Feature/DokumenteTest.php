<?php

namespace Tests\Feature;

use App\Enums\DokumentArt;
use App\Enums\DokumentStand;
use App\Enums\Rolle;
use App\Filament\Kunde\Resources\Dokumente\DokumentResource;
use App\Filament\Kunde\Resources\Dokumente\Pages\ViewDokument;
use App\Models\Customer;
use App\Models\Dokument;
use App\Models\Project;
use App\Models\User;
use App\Support\Ereignis;
use App\Support\Ereignisstrom;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Angebote, Rechnungen und Verträge am Kunden.
 *
 * Der Schwerpunkt liegt auf der Freigabe. Alles andere an dieser Funktion
 * ist Ablage; die eine Stelle, an der ein Fehler weh tut, ist die, an der
 * ein nicht freigegebenes Angebot beim Kunden landet — oder das eines
 * anderen Kunden.
 */
class DokumenteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    private function kundenzugang(Customer $kunde): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
        ]);
    }

    public function test_kunde_sieht_nur_freigegebene_dokumente(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $freigegeben = Dokument::factory()->for($kunde, 'customer')->freigegeben()
            ->create(['titel' => 'Rechnung August']);
        $entwurf = Dokument::factory()->for($kunde, 'customer')
            ->create(['titel' => 'Entwurf Angebot']);

        $sichtbar = Dokument::query()->sichtbarFuer($zugang)->pluck('id');

        $this->assertTrue($sichtbar->contains($freigegeben->id));
        $this->assertFalse($sichtbar->contains($entwurf->id));
    }

    public function test_kunde_sieht_dokumente_anderer_kunden_nicht(): void
    {
        $meiner = Customer::factory()->create();
        $fremder = Customer::factory()->create();
        $zugang = $this->kundenzugang($meiner);

        $meins = Dokument::factory()->for($meiner, 'customer')->freigegeben()->create();
        // Auch freigegeben — die Freigabe gilt für seinen Kunden, nicht für
        // alle.
        $fremd = Dokument::factory()->for($fremder, 'customer')->freigegeben()->create();

        $sichtbar = Dokument::query()->sichtbarFuer($zugang)->pluck('id');

        $this->assertTrue($sichtbar->contains($meins->id));
        $this->assertFalse($sichtbar->contains($fremd->id));
    }

    public function test_der_bereich_erscheint_erst_wenn_etwas_drin_steht(): void
    {
        // Der ausdrückliche Wunsch: kein leerer Menüpunkt, der ein Jahr lang
        // "kommt noch" sagt.
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $this->actingAs($zugang, 'kunde');
        $this->assertFalse(DokumentResource::canAccess());

        // Ein nicht freigegebenes Dokument zählt nicht — sonst tauchte der
        // Bereich auf, sobald wir intern etwas ablegen, und wäre für ihn leer.
        Dokument::factory()->for($kunde, 'customer')->create();
        $this->assertFalse(DokumentResource::canAccess());

        Dokument::factory()->for($kunde, 'customer')->freigegeben()->create();
        $this->assertTrue(DokumentResource::canAccess());
    }

    public function test_kunde_kann_ein_angebot_annehmen(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);
        // Damit die Meldung an jemanden geht und der Kreis geprüft ist.
        $this->admin();

        $angebot = Dokument::factory()->for($kunde, 'customer')->angebot()->freigegeben()
            ->create(['titel' => 'Relaunch Startseite', 'betrag' => 2380]);

        // Panel ausdrücklich setzen: eine Ressourcenseite des Kundenpanels
        // sucht ihre Aktionen sonst im gerade aktiven Panel, und das ist im
        // Test das interne.
        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(ViewDokument::class, ['record' => $angebot->getRouteKey()])
            ->callAction('annehmen')
            ->assertHasNoActionErrors();

        $angebot->refresh();

        $this->assertSame(DokumentStand::Angenommen, $angebot->stand);
        // Der Zeitstempel ist die Antwort auf "hat er zugesagt oder haben wir
        // das eingetragen" — ohne ihn wäre die Zusage nicht belegt.
        $this->assertNotNull($angebot->beantwortet_at);
        $this->assertSame($zugang->getKey(), $angebot->beantwortet_von);
    }

    public function test_ein_beantwortetes_angebot_laesst_sich_nicht_noch_einmal_beantworten(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $angebot = Dokument::factory()->for($kunde, 'customer')->angebot()->freigegeben()
            ->create(['stand' => DokumentStand::Angenommen]);

        $this->actingAs($zugang, 'kunde');

        $this->assertFalse($angebot->wartetAufAntwort());
        $this->assertFalse($zugang->can('beantworten', $angebot));
    }

    public function test_auf_eine_rechnung_antwortet_niemand(): void
    {
        // Nur Angebote sind entscheidbar. Eine Rechnung, die der Kunde per
        // Knopf auf "bezahlt" setzen könnte, wäre die Sorte Funktion, die
        // beim ersten Missverständnis Geld kostet.
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $rechnung = Dokument::factory()->for($kunde, 'customer')->freigegeben()->create();

        $this->actingAs($zugang, 'kunde');

        $this->assertFalse($rechnung->wartetAufAntwort());
        $this->assertFalse($zugang->can('beantworten', $rechnung));
    }

    public function test_die_datei_kommt_nur_an_berechtigte(): void
    {
        Storage::fake(Dokument::PLATTE);

        $kunde = Customer::factory()->create();
        $fremder = Customer::factory()->create();

        $dokument = Dokument::factory()->for($kunde, 'customer')->freigegeben()->create();
        Storage::disk(Dokument::PLATTE)->put($dokument->pfad, 'PDF');

        $eigener = $this->kundenzugang($kunde);
        $fremderZugang = $this->kundenzugang($fremder);

        $this->actingAs($eigener, 'kunde')
            ->get(route('kunde.dokument.zeigen', $dokument))
            ->assertOk();

        $this->actingAs($fremderZugang, 'kunde')
            ->get(route('kunde.dokument.zeigen', $dokument))
            ->assertForbidden();
    }

    public function test_ein_nicht_freigegebenes_dokument_gibt_es_auch_ueber_die_adresse_nicht(): void
    {
        // Die Freigabe steht in der Policy ein zweites Mal, weil eine
        // erratene Adresse nicht durch eine Liste geht.
        Storage::fake(Dokument::PLATTE);

        $kunde = Customer::factory()->create();
        $dokument = Dokument::factory()->for($kunde, 'customer')->create();
        Storage::disk(Dokument::PLATTE)->put($dokument->pfad, 'PDF');

        $this->actingAs($this->kundenzugang($kunde), 'kunde')
            ->get(route('kunde.dokument.zeigen', $dokument))
            ->assertForbidden();
    }

    public function test_mitarbeiter_sieht_dokumente_nur_seiner_kunden(): void
    {
        $mitarbeiter = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);

        $meiner = Customer::factory()->create();
        $projekt = Project::factory()->for($meiner, 'customer')->create();
        $projekt->mitarbeiter()->attach($mitarbeiter);

        $fremder = Customer::factory()->create();

        $meins = Dokument::factory()->for($meiner, 'customer')->create();
        $fremd = Dokument::factory()->for($fremder, 'customer')->create();

        $sichtbar = Dokument::query()->sichtbarFuer($mitarbeiter)->pluck('id');

        $this->assertTrue($sichtbar->contains($meins->id));
        $this->assertFalse($sichtbar->contains($fremd->id));
    }

    public function test_loeschen_nimmt_die_datei_mit(): void
    {
        Storage::fake(Dokument::PLATTE);

        $dokument = Dokument::factory()->create();
        Storage::disk(Dokument::PLATTE)->put($dokument->pfad, 'PDF');

        $dokument->delete();

        Storage::disk(Dokument::PLATTE)->assertMissing($dokument->pfad);
    }

    public function test_ueberfaellig_braucht_eine_frist(): void
    {
        $ohneFrist = Dokument::factory()->make([
            'stand' => DokumentStand::Offen,
            'faellig_am' => null,
        ]);

        $this->assertFalse($ohneFrist->istUeberfaellig());
        $this->assertTrue(Dokument::factory()->make(['faellig_am' => now()->subDay()])->istUeberfaellig());

        // Bezahlt ist nicht überfällig, auch wenn die Frist längst durch ist.
        $this->assertFalse(Dokument::factory()->make([
            'stand' => DokumentStand::Bezahlt,
            'faellig_am' => now()->subMonth(),
        ])->istUeberfaellig());

        // Storniert zählt nicht als offener Posten — sonst stünde am
        // Monatsende eine Zahl da, die niemand erklären kann.
        $this->assertFalse(DokumentStand::Storniert->istOffen());
    }

    public function test_die_angebotsantwort_steht_im_ereignisstrom(): void
    {
        // Der ausdrückliche Wunsch: nicht nur die Glocke, sondern auch der
        // Ticker. Die Glocke klickt man einmal weg, der Ticker bleibt den
        // Tag über offen.
        $admin = $this->admin();
        $kunde = Customer::factory()->create(['name' => 'Beispiel GmbH']);
        $zugang = $this->kundenzugang($kunde);

        Dokument::factory()
            ->for($kunde, 'customer')
            ->beantwortet($zugang)
            ->create(['titel' => 'Relaunch Startseite', 'betrag' => 2380]);

        $strom = Ereignisstrom::fuer($admin);
        $eintrag = $strom->firstWhere('typ', Ereignis::DOKUMENT);

        $this->assertNotNull($eintrag, 'Die Angebotsantwort fehlt im Ereignisstrom.');
        $this->assertSame('hat das Angebot angenommen', $eintrag->was);
        $this->assertSame($zugang->name, $eintrag->urheber());
        // Ohne Ticket braucht der Eintrag den Kunden als Bezug, sonst weiß
        // niemand, worum es geht.
        $this->assertNull($eintrag->ticket);
        $this->assertStringContainsString('Beispiel GmbH', $eintrag->kontext);
        $this->assertStringContainsString('2.380,00 €', $eintrag->kontext);
    }

    public function test_der_ereignisstrom_zeigt_keine_fremden_angebote(): void
    {
        $mitarbeiter = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);

        $meiner = Customer::factory()->create(['name' => 'Mein Kunde']);
        $projekt = Project::factory()->for($meiner, 'customer')->create();
        $projekt->mitarbeiter()->attach($mitarbeiter);

        $fremder = Customer::factory()->create(['name' => 'Fremder Kunde']);

        Dokument::factory()->for($meiner, 'customer')
            ->beantwortet($this->kundenzugang($meiner))->create();
        Dokument::factory()->for($fremder, 'customer')
            ->beantwortet($this->kundenzugang($fremder))->create();

        $kontexte = Ereignisstrom::fuer($mitarbeiter)
            ->where('typ', Ereignis::DOKUMENT)
            ->pluck('kontext')
            ->implode(' ');

        $this->assertStringContainsString('Mein Kunde', $kontexte);
        $this->assertStringNotContainsString('Fremder Kunde', $kontexte);
    }

    public function test_unbeantwortete_angebote_stehen_nicht_im_strom(): void
    {
        // Erst die Antwort ist das Ereignis. Ein hochgeladenes Angebot ist
        // etwas, das wir getan haben — das steht schon in der Kundenakte.
        $admin = $this->admin();
        $kunde = Customer::factory()->create();

        Dokument::factory()->for($kunde, 'customer')->angebot()->freigegeben()->create();

        $this->assertNull(
            Ereignisstrom::fuer($admin)->firstWhere('typ', Ereignis::DOKUMENT),
        );
    }

    public function test_zu_jeder_art_passen_nur_ihre_staende(): void
    {
        $this->assertContains(DokumentStand::Bezahlt, DokumentArt::Rechnung->staende());
        $this->assertNotContains(DokumentStand::Angenommen, DokumentArt::Rechnung->staende());

        $this->assertContains(DokumentStand::Angenommen, DokumentArt::Angebot->staende());
        $this->assertNotContains(DokumentStand::Bezahlt, DokumentArt::Angebot->staende());

        // Ein Vertrag hat keinen Stand — das Formular blendet das Feld aus.
        $this->assertSame([], DokumentArt::Vertrag->staende());
    }
}
