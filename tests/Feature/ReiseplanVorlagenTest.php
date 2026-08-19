<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Resources\ReiseplanVorlagen\ReiseplanVorlageResource;
use App\Models\Customer;
use App\Models\Meilenstein;
use App\Models\Project;
use App\Models\ReiseplanPunkt;
use App\Models\ReiseplanVorlage;
use App\Models\User;
use App\Support\MeilensteinVorlagen;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reiseplan-Vorlagen, seit dem 19.08.2026 in der Datenbank statt in
 * config/meilensteine.php.
 *
 * Der Umzug hatte einen einzigen Grund: die Texte stehen wörtlich beim
 * Kunden, und jede Änderung kostete vorher einen Deploy. Entsprechend liegt
 * der Schwerpunkt hier darauf, dass die Quelle wirklich gewechselt hat — und
 * dass eine geänderte Vorlage laufende Kundenprojekte NICHT umschreibt.
 */
class ReiseplanVorlagenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    public function test_die_drei_vorlagen_stehen_nach_der_migration_in_der_datenbank(): void
    {
        $this->assertSame(3, ReiseplanVorlage::query()->count());

        $schluessel = ReiseplanVorlage::query()->pluck('schluessel')->all();

        $this->assertEqualsCanonicalizing(['website', 'app', 'betreuung'], $schluessel);
    }

    public function test_die_auswahl_kommt_aus_der_datenbank(): void
    {
        ReiseplanVorlage::create([
            'name' => 'Wartungsvertrag',
            'schluessel' => 'wartung',
            'sortierung' => 9,
        ]);

        $this->assertArrayHasKey('wartung', MeilensteinVorlagen::auswahl());
        $this->assertSame('Wartungsvertrag', MeilensteinVorlagen::auswahl()['wartung']);
    }

    public function test_geaenderte_texte_wirken_sofort(): void
    {
        $vorlage = ReiseplanVorlage::query()->where('schluessel', 'betreuung')->firstOrFail();

        $vorlage->punkte()->first()->update(['titel' => 'Schlüsselübergabe']);

        $this->assertSame(
            'Schlüsselübergabe',
            MeilensteinVorlagen::punkte('betreuung')->first()['titel'],
        );
    }

    /**
     * Die wichtigste Zusicherung der ganzen Funktion: eine Vorlage ist eine
     * Starthilfe und hat danach nichts mehr zu sagen. Schriebe sie
     * rückwirkend Titel um, änderte sich der Reiseplan eines Kunden, ohne
     * dass jemand sein Projekt angefasst hat.
     */
    public function test_eine_geaenderte_vorlage_schreibt_laufende_projekte_nicht_um(): void
    {
        $projekt = Project::factory()->for(Customer::factory(), 'customer')->create();

        Meilenstein::create([
            'project_id' => $projekt->getKey(),
            'titel' => 'Erstgespräch',
        ]);

        $vorlage = ReiseplanVorlage::query()->where('schluessel', 'website')->firstOrFail();
        $vorlage->punkte()->where('titel', 'Erstgespräch')->update(['titel' => 'Kennenlernen']);

        $this->assertSame('Erstgespräch', $projekt->meilensteine()->value('titel'));
    }

    /** Ein Auswahlfeld ohne Vorauswahl sieht aus, als wäre nichts geladen. */
    public function test_es_gibt_immer_eine_vorgabe(): void
    {
        $this->assertSame('website', MeilensteinVorlagen::vorgabe());

        ReiseplanVorlage::query()->update(['ist_vorgabe' => false]);

        // Ohne Markierung fällt sie auf die erste zurück, statt null zu geben.
        $this->assertNotNull(MeilensteinVorlagen::vorgabe());
    }

    /**
     * Zwei vorausgewählte Vorlagen wären kein Fehler, den man sieht — das
     * Formular nähme stillschweigend die erste. Deshalb steht die Regel am
     * Modell und nicht im Formular.
     */
    public function test_es_gibt_hoechstens_eine_vorgabe(): void
    {
        $neu = ReiseplanVorlage::create([
            'name' => 'Wartung',
            'schluessel' => 'wartung',
            'ist_vorgabe' => true,
        ]);

        $this->assertSame(1, ReiseplanVorlage::query()->where('ist_vorgabe', true)->count());
        $this->assertSame($neu->getKey(), ReiseplanVorlage::query()->where('ist_vorgabe', true)->value('id'));
    }

    public function test_geloeschte_vorlage_nimmt_ihre_etappen_mit(): void
    {
        $vorlage = ReiseplanVorlage::query()->where('schluessel', 'betreuung')->firstOrFail();
        $anzahl = $vorlage->punkte()->count();

        $this->assertGreaterThan(0, $anzahl);

        $vorlage->delete();

        $this->assertSame(0, ReiseplanPunkt::query()->where('reiseplan_vorlage_id', $vorlage->getKey())->count());
    }

    // ------------------------------------------------------------ Die Fehler

    /**
     * Der schwerste der Fehler, die beim Umzug mitkorrigiert wurden: die
     * Texte schickten den Kunden viermal auf ein "Ticket" — ein Wort, das es
     * in seinem Bereich gar nicht gibt. Bei ihm heißt es "Anliegen".
     */
    public function test_kein_vorlagentext_spricht_von_tickets(): void
    {
        $texte = ReiseplanPunkt::query()
            ->get()
            ->flatMap(fn (ReiseplanPunkt $p) => [$p->titel, (string) $p->beschreibung]);

        foreach ($texte as $text) {
            $this->assertStringNotContainsStringIgnoringCase(
                'ticket',
                $text,
                'Der Kunde sieht das Wort "Ticket" nirgends — bei ihm heißt es "Anliegen".',
            );
        }
    }

    public function test_die_rechtschreibfehler_sind_raus(): void
    {
        $titel = ReiseplanPunkt::query()->pluck('titel');

        $this->assertContains('Seite fertig zum Gegenlesen', $titel);
        $this->assertContains('Unser Designvorschlag', $titel);
        $this->assertNotContains('Seite fertig zum gegenlesen', $titel);
        $this->assertNotContains('Unser Design Vorschlag', $titel);
    }

    // ------------------------------------------------------------- Der Zugang

    public function test_nur_der_administrator_kommt_an_die_vorlagen(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');
        $this->assertTrue(ReiseplanVorlageResource::canAccess());

        $kevin = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);

        $this->actingAs($kevin);
        $this->assertFalse(ReiseplanVorlageResource::canAccess());
    }
}
