<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Kunde\Widgets\Messe as MesseWidget;
use App\Filament\Resources\Treffen\Pages\ListTreffen;
use App\Filament\Resources\Treffen\TreffenResource;
use App\Filament\Widgets\MeineTreffen;
use App\Models\Customer;
use App\Models\Treffen;
use App\Models\User;
use App\Support\Kalender;
use App\Support\Messe;
use App\Support\Wochenplan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die Messe — Treffen mit einem Kunden.
 *
 * Der Schwerpunkt liegt wie bei den Dokumenten auf der Freigabe: die eine
 * Stelle, an der ein Fehler weh tut, ist die, an der ein noch nicht
 * verabredeter Termin beim Kunden auftaucht — oder der eines anderen Kunden.
 *
 * Dazu der Kalendereintrag. Er verlässt das Haus und landet in fremden
 * Programmen; was dort schiefgeht, sieht man hier nie.
 */
class MesseTest extends TestCase
{
    use RefreshDatabase;

    private function kundenzugang(Customer $kunde): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
        ]);
    }

    // ---------------------------------------------------------------- Sicht

    public function test_kunde_sieht_nur_eingeladene_treffen(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        Treffen::factory()->for($kunde, 'customer')->eingeladen()
            ->create(['titel' => 'Quartalsgespräch']);
        Treffen::factory()->for($kunde, 'customer')
            ->create(['titel' => 'Interner Bleistiftstrich']);

        $sichtbar = Treffen::query()->sichtbarFuer($zugang)->pluck('titel');

        $this->assertContains('Quartalsgespräch', $sichtbar);
        $this->assertNotContains('Interner Bleistiftstrich', $sichtbar);
    }

    public function test_kunde_sieht_die_treffen_anderer_kunden_nicht(): void
    {
        $meiner = Customer::factory()->create();
        $fremder = Customer::factory()->create();

        $zugang = $this->kundenzugang($meiner);

        // Ausdrücklich freigegeben — die Freigabe allein darf nicht reichen.
        Treffen::factory()->for($fremder, 'customer')->eingeladen()
            ->create(['titel' => 'Fremdes Treffen']);

        $this->assertSame(0, Treffen::query()->sichtbarFuer($zugang)->count());
    }

    public function test_widget_zeigt_das_naechste_treffen(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        Treffen::factory()->for($kunde, 'customer')->eingeladen()->create([
            'titel' => 'Abnahme der Startseite',
            'beginnt_am' => now()->addDays(2)->setTime(14, 0),
        ]);

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(MesseWidget::class)
            ->assertOk()
            ->assertSee('Abnahme der Startseite')
            ->assertSee('An Bord gehen');
    }

    /**
     * Ein Kasten, in dem elf Monate lang "keine Termine" steht, ist derselbe
     * Fehler wie ein leerer Menüpunkt — man übergeht ihn dann auch, wenn
     * zum ersten Mal etwas darin steht.
     */
    public function test_ohne_treffen_erscheint_die_karte_gar_nicht(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(MesseWidget::class)
            ->assertOk()
            ->assertDontSee('An Bord der');
    }

    public function test_vergangene_treffen_verschwinden_aus_der_karte(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        Treffen::factory()->for($kunde, 'customer')->eingeladen()->vergangen()
            ->create(['titel' => 'Letzte Woche']);

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(MesseWidget::class)->assertOk()->assertDontSee('Letzte Woche');
    }

    /**
     * Ein Treffen um vierzehn Uhr ist um Viertel nach nicht vergangen,
     * sondern mittendrin — und darf dem Kunden nicht unter den Fingern
     * verschwinden, während er den Knopf sucht.
     */
    public function test_laufendes_treffen_bleibt_stehen(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $treffen = Treffen::factory()->for($kunde, 'customer')->eingeladen()->laufend()
            ->create(['titel' => 'Läuft gerade']);

        $this->assertTrue($treffen->laeuft());
        $this->assertFalse($treffen->istVorbei());

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(MesseWidget::class)->assertOk()->assertSee('Läuft gerade');
    }

    /**
     * Abgesagt statt gelöscht: ein verschwundenes Treffen ist keine Absage,
     * und der Kunde säße trotzdem davor.
     */
    public function test_abgesagtes_treffen_bleibt_sichtbar(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        Treffen::factory()->for($kunde, 'customer')->eingeladen()->abgesagt()
            ->create(['titel' => 'Fällt aus']);

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(MesseWidget::class)
            ->assertOk()
            ->assertSee('Fällt aus')
            ->assertSee('Abgesagt');
    }

    // ----------------------------------------------------- Kalendereintrag

    public function test_kalendereintrag_traegt_zeiten_in_utc(): void
    {
        // 14:00 Ortszeit im August (Sommerzeit, +2) sind 12:00 UTC. Genau
        // hier ging es früher schief: ohne Umrechnung stünde der Termin bei
        // einem Kunden mit anderer Zeitzone verschoben im Kalender.
        $treffen = Treffen::factory()->eingeladen()->create([
            'beginnt_am' => '2026-08-20 14:00:00',
            'dauer_minuten' => 60,
        ]);

        $ics = Kalender::fuer($treffen);

        $this->assertStringContainsString('DTSTART:20260820T120000Z', $ics);
        $this->assertStringContainsString('DTEND:20260820T130000Z', $ics);
    }

    public function test_kalendereintrag_behaelt_seine_kennung_ueber_aenderungen(): void
    {
        // Sonst legt sich der verschobene Termin im Kalender des Kunden
        // neben den alten, statt ihn zu ersetzen — und er erscheint zur
        // alten Zeit.
        $treffen = Treffen::factory()->eingeladen()->create();

        $vorher = Kalender::fuer($treffen);

        $treffen->update(['beginnt_am' => now()->addWeeks(2)]);

        $nachher = Kalender::fuer($treffen->fresh());

        $kennung = 'UID:treffen-'.$treffen->getKey().'@nils-digital.de';

        $this->assertStringContainsString($kennung, $vorher);
        $this->assertStringContainsString($kennung, $nachher);
    }

    public function test_abgesagtes_treffen_geht_als_absage_in_den_kalender(): void
    {
        $treffen = Treffen::factory()->eingeladen()->abgesagt()->create();

        $this->assertStringContainsString('STATUS:CANCELLED', Kalender::fuer($treffen));
    }

    /**
     * Semikolon und Komma haben im Format eine Bedeutung. Ein Titel wie
     * "Abnahme, Teil 2" zerlegte den Eintrag sonst in zwei Felder.
     */
    public function test_sonderzeichen_im_titel_zerlegen_den_eintrag_nicht(): void
    {
        $treffen = Treffen::factory()->eingeladen()->create([
            'titel' => 'Abnahme, Teil 2; final',
        ]);

        $ics = Kalender::fuer($treffen);

        $this->assertStringContainsString('Abnahme\\, Teil 2\\; final', $ics);
    }

    public function test_kunde_kommt_nicht_an_den_kalender_eines_fremden_treffens(): void
    {
        $meiner = Customer::factory()->create();
        $fremder = Customer::factory()->create();

        $zugang = $this->kundenzugang($meiner);

        $treffen = Treffen::factory()->for($fremder, 'customer')->eingeladen()->create();

        $this->actingAs($zugang, 'kunde')
            ->get(route('kunde.treffen.kalender', $treffen))
            ->assertForbidden();
    }

    public function test_kunde_kommt_nicht_an_den_kalender_eines_nicht_freigegebenen_treffens(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        // Sein eigener Kunde, aber noch nicht eingeladen. Die Adresse trägt
        // eine laufende Nummer — also genau die Sorte, die man durchprobiert.
        $treffen = Treffen::factory()->for($kunde, 'customer')->create();

        $this->actingAs($zugang, 'kunde')
            ->get(route('kunde.treffen.kalender', $treffen))
            ->assertForbidden();
    }

    public function test_kunde_bekommt_den_kalender_seines_treffens(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $treffen = Treffen::factory()->for($kunde, 'customer')->eingeladen()->create();

        $this->actingAs($zugang, 'kunde')
            ->get(route('kunde.treffen.kalender', $treffen))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    }

    // ------------------------------------------------------ Benachrichtigung

    public function test_die_freigabe_ist_die_einladung(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        // Beim Anlegen noch nicht freigegeben: es darf nichts hinausgehen.
        $treffen = Treffen::factory()->for($kunde, 'customer')->create();

        $this->assertSame(0, $zugang->notifications()->count());

        $treffen->update(['kunden_sichtbar' => true]);

        $this->assertSame(1, $zugang->fresh()->notifications()->count());
    }

    public function test_verschieben_meldet_den_neuen_termin(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $treffen = Treffen::factory()->for($kunde, 'customer')->eingeladen()->create();

        $this->assertSame(1, $zugang->notifications()->count());

        $treffen->update(['beginnt_am' => now()->addWeeks(3)]);

        $this->assertSame(2, $zugang->fresh()->notifications()->count());
    }

    /**
     * Eine Meldung für jede getippte Kleinigkeit ist eine, die der Kunde
     * bald übergeht — und dann übersieht er auch die Absage.
     */
    public function test_eine_geaenderte_notiz_meldet_nichts(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $treffen = Treffen::factory()->for($kunde, 'customer')->eingeladen()->create();

        $treffen->update(['notiz' => 'Noch ein Punkt für die Tagesordnung']);

        $this->assertSame(1, $zugang->fresh()->notifications()->count());
    }

    public function test_absage_meldet_sich_als_absage(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $treffen = Treffen::factory()->for($kunde, 'customer')->eingeladen()->create();
        $treffen->update(['abgesagt_at' => now()]);

        // Über alle Meldungen und nicht über die jüngste: Einladung und
        // Absage entstehen im Test in derselben Sekunde, und dann ist
        // "latest" eine Frage des Zufalls. Im Betrieb liegen Tage dazwischen.
        $titel = $zugang->fresh()->notifications
            ->map(fn ($meldung) => strtolower($meldung->data['title'] ?? ''));

        $this->assertTrue(
            $titel->contains(fn (string $t) => str_contains($t, 'abgesagt')),
            'Keine Meldung nennt die Absage. Vorhanden: '.$titel->join(' | '),
        );
    }

    public function test_ein_nicht_freigegebenes_treffen_meldet_auch_die_absage_nicht(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $treffen = Treffen::factory()->for($kunde, 'customer')->create();
        $treffen->update(['abgesagt_at' => now()]);

        $this->assertSame(0, $zugang->fresh()->notifications()->count());
    }

    // ------------------------------------------------------------ Die Crew

    private function mitarbeiter(string $name = 'Kevin'): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
            'name' => $name,
        ]);
    }

    public function test_wer_dazukommt_wird_benachrichtigt(): void
    {
        $kevin = $this->mitarbeiter();
        $treffen = Treffen::factory()->create();

        Messe::crewSetzen($treffen, [$kevin->getKey()]);

        $this->assertSame(1, $kevin->fresh()->notifications()->count());
        $this->assertTrue($treffen->crew()->whereKey($kevin->getKey())->exists());
    }

    /**
     * Wer schon dabei war, hat seine Einladung. Sonst schickte ein Termin,
     * an dem man dreimal die Beschreibung nachbessert, dreimal dieselbe
     * Meldung — und nach der zweiten liest sie niemand mehr.
     */
    public function test_wer_schon_dabei_war_bekommt_nichts_noch_einmal(): void
    {
        $kevin = $this->mitarbeiter();
        $treffen = Treffen::factory()->create();

        Messe::crewSetzen($treffen, [$kevin->getKey()]);
        Messe::crewSetzen($treffen, [$kevin->getKey()]);

        $this->assertSame(1, $kevin->fresh()->notifications()->count());
    }

    /** Wer sich selbst einträgt, füllt gerade das Formular aus. */
    public function test_sich_selbst_eintragen_meldet_nichts(): void
    {
        $nils = $this->mitarbeiter('Nils');
        $this->actingAs($nils);

        $treffen = Treffen::factory()->create();
        Messe::crewSetzen($treffen, [$nils->getKey()]);

        $this->assertSame(0, $nils->fresh()->notifications()->count());
    }

    public function test_verschieben_meldet_sich_auch_bei_der_crew(): void
    {
        $kevin = $this->mitarbeiter();
        $treffen = Treffen::factory()->create();
        $treffen->crew()->attach($kevin);

        $treffen->update(['beginnt_am' => now()->addWeeks(4)]);

        $titel = $kevin->fresh()->notifications
            ->map(fn ($meldung) => $meldung->data['title'] ?? '');

        $this->assertTrue($titel->contains(fn (string $t) => str_contains($t, 'Verschoben')));
    }

    /**
     * Auch ein noch nicht freigegebener Termin steht im Kalender der Crew —
     * die Absage muss dort ankommen, unabhängig vom Kunden.
     */
    public function test_crew_erfaehrt_die_absage_auch_ohne_freigabe(): void
    {
        $kevin = $this->mitarbeiter();
        $treffen = Treffen::factory()->create();
        $treffen->crew()->attach($kevin);

        $treffen->update(['abgesagt_at' => now()]);

        $titel = $kevin->fresh()->notifications
            ->map(fn ($meldung) => $meldung->data['title'] ?? '');

        $this->assertTrue($titel->contains(fn (string $t) => str_contains($t, 'Abgesagt')));
    }

    public function test_wache_zeigt_nur_meine_treffen(): void
    {
        $kevin = $this->mitarbeiter();
        $nils = $this->mitarbeiter('Nils');

        $meins = Treffen::factory()->create(['titel' => 'Mein Termin']);
        $meins->crew()->attach($kevin);

        $fremdes = Treffen::factory()->create(['titel' => 'Fremder Termin']);
        $fremdes->crew()->attach($nils);

        $this->actingAs($kevin);
        Filament::setCurrentPanel('admin');

        Livewire::test(MeineTreffen::class)
            ->assertOk()
            ->assertSee('Mein Termin')
            ->assertDontSee('Fremder Termin');
    }

    public function test_wache_bleibt_leer_ohne_eigene_treffen(): void
    {
        $kevin = $this->mitarbeiter();

        $this->actingAs($kevin);
        Filament::setCurrentPanel('admin');

        Livewire::test(MeineTreffen::class)
            ->assertOk()
            ->assertDontSee('Meine Treffen');
    }

    // ------------------------------------------------- Treffen ohne Kunden

    /**
     * Der Fall, um den es geht: eine Team-Besprechung darf bei keinem Kunden
     * auftauchen. Er ergibt sich aus dem Vergleich auf customer_id von
     * selbst — und steht trotzdem als Test da, weil "ergibt sich von selbst"
     * die Begründung ist, die eine spätere Änderung aushebelt.
     */
    public function test_interne_treffen_bleiben_intern(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        // Ausdrücklich mit gesetztem Freigabe-Schalter: auch der darf ein
        // Treffen ohne Kunden nicht nach außen tragen.
        Treffen::factory()->create([
            'customer_id' => null,
            'kunden_sichtbar' => true,
            'titel' => 'Wochenplanung',
        ]);

        $this->assertSame(0, Treffen::query()->sichtbarFuer($zugang)->count());
    }

    public function test_admin_sieht_interne_treffen(): void
    {
        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);

        Treffen::factory()->create(['customer_id' => null, 'titel' => 'Retro']);

        $this->assertSame(1, Treffen::query()->sichtbarFuer($admin)->count());
    }

    /** Ohne die Crew-Regel wäre eine Besprechung für den unsichtbar, der darin sitzt. */
    public function test_mitarbeiter_sieht_interne_treffen_nur_wenn_er_dabei_ist(): void
    {
        $kevin = $this->mitarbeiter();
        $nils = $this->mitarbeiter('Nils');

        $meins = Treffen::factory()->create(['customer_id' => null, 'titel' => 'Mit mir']);
        $meins->crew()->attach($kevin);

        $ohneMich = Treffen::factory()->create(['customer_id' => null, 'titel' => 'Ohne mich']);
        $ohneMich->crew()->attach($nils);

        $sichtbar = Treffen::query()->sichtbarFuer($kevin)->pluck('titel');

        $this->assertContains('Mit mir', $sichtbar);
        $this->assertNotContains('Ohne mich', $sichtbar);
    }

    public function test_kunde_kommt_nicht_an_den_kalender_eines_internen_treffens(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $treffen = Treffen::factory()->create([
            'customer_id' => null,
            'kunden_sichtbar' => true,
        ]);

        $this->actingAs($zugang, 'kunde')
            ->get(route('kunde.treffen.kalender', $treffen))
            ->assertForbidden();
    }

    /** Ein internes Treffen hat keinen Kunden, den man benachrichtigen könnte. */
    public function test_internes_treffen_meldet_sich_bei_keinem_kunden(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        Treffen::factory()->create(['customer_id' => null, 'kunden_sichtbar' => true]);

        $this->assertSame(0, $zugang->fresh()->notifications()->count());
    }

    public function test_interne_treffen_stehen_in_der_wochenvorschau(): void
    {
        $kevin = $this->mitarbeiter();

        $treffen = Treffen::factory()->create([
            'customer_id' => null,
            'titel' => 'Wochenplanung',
            'beginnt_am' => now()->addDays(2),
        ]);
        $treffen->crew()->attach($kevin);

        $termine = Wochenplan::fuer($kevin);

        $this->assertSame(1, $termine->count());
        $this->assertSame('nur wir', $termine->first()->zusatz);
    }

    // ------------------------------------------------ Anlegen ueber die Seite

    /**
     * Der Weg durch die Oberflaeche, nicht nur durchs Modell.
     *
     * Der Grund fuer diesen Test ist ein Fehler, der genau hier steckte und
     * durch keinen Modelltest aufgefallen waere: Filament loest die
     * Parameter einer Closure ueber ihren **Namen** auf. Die Formulardaten
     * heissen $data — mit $daten scheiterte das Anlegen mit einer
     * BindingResolutionException, und zwar an beiden Stellen, an denen man
     * ein Treffen ansetzt.
     */
    public function test_treffen_ohne_kunden_ueber_die_seite_anlegen(): void
    {
        $nils = $this->mitarbeiter('Nils');
        $kevin = $this->mitarbeiter('Kevin');

        $this->actingAs($nils);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListTreffen::class)
            ->callAction('create', data: [
                'titel' => 'Wochenplanung',
                'customer_id' => null,
                'beginnt_am' => now()->addDays(2)->setTime(9, 0)->format('Y-m-d H:i:s'),
                'dauer_minuten' => 30,
                'crew_ids' => [$nils->getKey(), $kevin->getKey()],
            ])
            ->assertHasNoActionErrors();

        $treffen = Treffen::query()->where('titel', 'Wochenplanung')->firstOrFail();

        $this->assertTrue($treffen->istIntern());
        $this->assertSame($nils->getKey(), $treffen->erstellt_von);
        $this->assertEqualsCanonicalizing(
            [$nils->getKey(), $kevin->getKey()],
            $treffen->crew()->pluck('users.id')->all(),
        );

        // Kevin wurde dazugenommen und erfaehrt es; Nils hat das Formular
        // selbst ausgefuellt und bekommt nichts.
        $this->assertSame(1, $kevin->fresh()->notifications()->count());
        $this->assertSame(0, $nils->fresh()->notifications()->count());
    }

    public function test_treffen_mit_kunden_ueber_die_seite_anlegen(): void
    {
        $nils = $this->mitarbeiter('Nils');
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        // Ohne Zuordnung steht der Kunde gar nicht erst in seiner Auswahl —
        // die Liste kommt aus Customer::sichtbarFuer. Das ist richtig so und
        // war der Grund, warum dieser Test beim ersten Lauf abgewiesen wurde.
        $kunde->mitarbeiter()->attach($nils);

        $this->actingAs($nils);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListTreffen::class)
            ->callAction('create', data: [
                'titel' => 'Quartalsgespraech',
                'customer_id' => $kunde->getKey(),
                'beginnt_am' => now()->addDays(3)->setTime(14, 0)->format('Y-m-d H:i:s'),
                'dauer_minuten' => 45,
                'kunden_sichtbar' => true,
                'crew_ids' => [$nils->getKey()],
            ])
            ->assertHasNoActionErrors();

        $treffen = Treffen::query()->where('titel', 'Quartalsgespraech')->firstOrFail();

        $this->assertFalse($treffen->istIntern());
        $this->assertTrue($treffen->kunden_sichtbar);

        // Freigegeben heisst eingeladen.
        $this->assertSame(1, $zugang->fresh()->notifications()->count());
    }

    /** Der Kundenzugang hat auf dieser Seite nichts verloren. */
    public function test_kunde_kommt_nicht_auf_die_messe_seite(): void
    {
        $zugang = $this->kundenzugang(Customer::factory()->create());

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        $this->assertFalse(TreffenResource::canAccess());
    }
}
