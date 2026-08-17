<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Enums\Unterhaltungsart;
use App\Filament\Kunde\Pages\Nachrichten as KundenNachrichten;
use App\Filament\Pages\Nachrichten as InterneNachrichten;
use App\Models\Customer;
use App\Models\Nachricht;
use App\Models\Project;
use App\Models\Unterhaltung;
use App\Models\User;
use App\Support\Unterhaltungen;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Der Chat neben den Tickets.
 *
 * Geprüft wird vor allem, wer NICHT mitliest. Ein Verlauf, der nicht aufgeht,
 * fällt beim ersten Klick auf; eine fremde Nachricht in der eigenen Liste
 * fällt niemandem auf, weil die Liste ja etwas anzeigt — dieselbe Überlegung
 * wie im KundenbereichTest, und hier wiegt sie schwerer: an einem Ticket
 * hängt wenigstens ein Projekt, an einer Unterhaltung nichts als die
 * Zuordnung.
 */
class NachrichtenTest extends TestCase
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

    private function kunde(?Customer $customer = null): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => ($customer ?? Customer::factory()->create())->getKey(),
        ]);
    }

    /** Ein Verlauf mit einer Nachricht darin — sonst gilt er als nicht begonnen. */
    private function verlaufMit(Customer $kunde, User $absender, string $text = 'Hallo'): Unterhaltung
    {
        $unterhaltung = Unterhaltungen::fuerKunden($kunde);

        Nachricht::create([
            'unterhaltung_id' => $unterhaltung->getKey(),
            'user_id' => $absender->getKey(),
            'text' => $text,
        ]);

        return $unterhaltung->fresh();
    }

    // --- Wer mitliest -------------------------------------------------

    public function test_kunde_sieht_nur_seinen_eigenen_verlauf(): void
    {
        $meiner = Customer::factory()->create();
        $fremder = Customer::factory()->create();

        $kunde = $this->kunde($meiner);
        $admin = $this->admin();

        $this->verlaufMit($meiner, $admin, 'Für meinen Kunden');
        $this->verlaufMit($fremder, $admin, 'Für einen anderen');

        $sichtbar = Unterhaltungen::fuer($kunde);

        $this->assertCount(1, $sichtbar);
        $this->assertSame($meiner->getKey(), $sichtbar->first()->customer_id);
    }

    public function test_mitarbeiter_sieht_kundenverlauf_nur_bei_zuordnung(): void
    {
        $kundeMitZuordnung = Customer::factory()->create();
        $fremderKunde = Customer::factory()->create();

        $mitarbeiter = $this->mitarbeiter();
        $mitarbeiter->customers()->attach($kundeMitZuordnung);

        $admin = $this->admin();

        $meiner = $this->verlaufMit($kundeMitZuordnung, $admin);
        $fremder = $this->verlaufMit($fremderKunde, $admin);

        $sichtbar = Unterhaltungen::fuer($mitarbeiter)->pluck('id');

        $this->assertTrue($sichtbar->contains($meiner->getKey()));
        $this->assertFalse($sichtbar->contains($fremder->getKey()), 'Fremder Kundenverlauf ist sichtbar.');

        $this->assertTrue($mitarbeiter->can('view', $meiner));
        $this->assertFalse($mitarbeiter->can('view', $fremder));
    }

    public function test_zuordnung_ueber_ein_projekt_reicht_ebenfalls(): void
    {
        // Dieselbe Regel wie bei Tickets und Zeiten: wer an einem Projekt des
        // Kunden hängt, ist zuständig. Stünde hier eine eigene Regel, liefe
        // sie beim nächsten Umbau der Zuordnungen auseinander.
        $kunde = Customer::factory()->create();
        $projekt = Project::factory()->for($kunde, 'customer')->create();

        $mitarbeiter = $this->mitarbeiter();
        $projekt->mitarbeiter()->attach($mitarbeiter);

        $verlauf = $this->verlaufMit($kunde, $this->admin());

        $this->assertTrue($mitarbeiter->can('view', $verlauf));
    }

    public function test_interner_verlauf_bleibt_auch_vor_dem_administrator_zu(): void
    {
        // Die eine Stelle, an der "Administrator sieht alles" nicht gilt. Ein
        // interner Draht, bei dem der Chef grundsätzlich mitliest, wird nach
        // dem ersten Mal nicht mehr benutzt.
        $einer = $this->mitarbeiter();
        $anderer = $this->mitarbeiter();
        $admin = $this->admin();

        $verlauf = Unterhaltungen::zwischen($einer, $anderer);

        Nachricht::create([
            'unterhaltung_id' => $verlauf->getKey(),
            'user_id' => $einer->getKey(),
            'text' => 'Unter uns',
        ]);

        $this->assertTrue($einer->can('view', $verlauf));
        $this->assertTrue($anderer->can('view', $verlauf));
        $this->assertFalse($admin->can('view', $verlauf->fresh()), 'Der Administrator liest mit.');

        $this->assertFalse(Unterhaltungen::fuer($admin)->pluck('id')->contains($verlauf->getKey()));
    }

    // --- Anlegen ------------------------------------------------------

    public function test_je_kunde_entsteht_nur_ein_verlauf(): void
    {
        $kunde = Customer::factory()->create();

        $erster = Unterhaltungen::fuerKunden($kunde);
        $zweiter = Unterhaltungen::fuerKunden($kunde->getKey());

        $this->assertTrue($erster->is($zweiter));
        $this->assertSame(1, Unterhaltung::query()->count());
    }

    public function test_interner_verlauf_wird_aus_beiden_richtungen_gefunden(): void
    {
        // Ohne die Suche über beide Beteiligten begänne die Antwort einen
        // zweiten Faden, und die Frage stünde im ersten.
        $einer = $this->mitarbeiter();
        $anderer = $this->mitarbeiter();

        $hin = Unterhaltungen::zwischen($einer, $anderer);
        $zurueck = Unterhaltungen::zwischen($anderer, $einer);

        $this->assertTrue($hin->is($zurueck));
        $this->assertSame(1, Unterhaltung::query()->where('art', Unterhaltungsart::Intern->value)->count());
    }

    public function test_ein_verlauf_ohne_nachricht_steht_in_keiner_liste(): void
    {
        // Er entsteht, sobald ein Kunde den Bereich öffnet. Stünde er dann
        // schon in unserer Liste, wäre die nach einem Monat eine Kundenliste.
        $kunde = Customer::factory()->create();
        Unterhaltungen::fuerKunden($kunde);

        $this->assertCount(0, Unterhaltungen::fuer($this->admin()));
    }

    // --- Ungelesen ----------------------------------------------------

    public function test_eigene_nachrichten_zaehlen_nicht_als_ungelesen(): void
    {
        $kunde = Customer::factory()->create();
        $admin = $this->admin();

        $verlauf = $this->verlaufMit($kunde, $admin, 'Von uns');

        $this->assertSame(0, $verlauf->ungeleseneFuer($admin));
    }

    public function test_ungelesenes_wird_gezaehlt_und_beim_oeffnen_zurueckgesetzt(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kunde($kunde);
        $admin = $this->admin();

        $this->verlaufMit($kunde, $zugang, 'Erste Frage');
        $this->verlaufMit($kunde, $zugang, 'Und noch eine');

        $this->assertSame(2, Unterhaltungen::ungelesen($admin));

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(InterneNachrichten::class)->assertOk();

        $this->assertSame(0, Unterhaltungen::ungelesen($admin->fresh()));
    }

    public function test_ungelesenes_eines_fremden_kunden_zaehlt_nicht_mit(): void
    {
        $meiner = Customer::factory()->create();
        $fremder = Customer::factory()->create();

        $mitarbeiter = $this->mitarbeiter();
        $mitarbeiter->customers()->attach($meiner);

        $this->verlaufMit($meiner, $this->kunde($meiner), 'Für dich');
        $this->verlaufMit($fremder, $this->kunde($fremder), 'Nicht für dich');

        $this->assertSame(1, Unterhaltungen::ungelesen($mitarbeiter));
    }

    // --- Schreiben ----------------------------------------------------

    public function test_kunde_schreibt_und_die_zustaendigen_erfahren_davon(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kunde($kunde);

        $admin = $this->admin();
        $zustaendig = $this->mitarbeiter();
        $zustaendig->customers()->attach($kunde);
        $unbeteiligt = $this->mitarbeiter();
        $andererKunde = $this->kunde();

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(KundenNachrichten::class)
            ->set('entwurf', 'Wann passt Ihnen ein Termin?')
            ->call('senden')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('nachrichten', [
            'user_id' => $zugang->getKey(),
            'text' => 'Wann passt Ihnen ein Termin?',
        ]);

        $this->assertSame(1, $admin->notifications()->count(), 'Der Administrator erfährt nichts.');
        $this->assertSame(1, $zustaendig->notifications()->count(), 'Der Zuständige erfährt nichts.');
        $this->assertSame(0, $unbeteiligt->notifications()->count(), 'Ein Unbeteiligter wird benachrichtigt.');
        $this->assertSame(0, $andererKunde->notifications()->count(), 'Ein fremder Kunde wird benachrichtigt.');
        $this->assertSame(0, $zugang->notifications()->count(), 'Der Absender benachrichtigt sich selbst.');
    }

    public function test_antwort_von_uns_erreicht_alle_zugaenge_des_kunden(): void
    {
        $kunde = Customer::factory()->create();
        $einer = $this->kunde($kunde);
        $zweiter = $this->kunde($kunde);
        $fremder = $this->kunde();

        $admin = $this->admin();
        $verlauf = Unterhaltungen::fuerKunden($kunde);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(InterneNachrichten::class)
            ->call('oeffnen', $verlauf->getKey())
            ->set('entwurf', 'Donnerstag um zehn?')
            ->call('senden')
            ->assertHasNoErrors();

        $this->assertSame(1, $einer->notifications()->count());
        $this->assertSame(1, $zweiter->notifications()->count());
        $this->assertSame(0, $fremder->notifications()->count(), 'Ein fremder Kunde wird benachrichtigt.');
    }

    public function test_eine_neue_kundenunterhaltung_beginnt_mit_dem_knopf_darueber(): void
    {
        $kunde = Customer::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(InterneNachrichten::class)
            ->callAction('mitKunde', [
                'customer_id' => $kunde->getKey(),
                'text' => 'Kurze Rückfrage zur Rechnung',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('nachrichten', ['text' => 'Kurze Rückfrage zur Rechnung']);
        $this->assertSame(1, Unterhaltung::query()->where('customer_id', $kunde->getKey())->count());
    }

    public function test_ein_mitarbeiter_kann_keine_unterhaltung_mit_einem_fremden_kunden_beginnen(): void
    {
        $fremderKunde = Customer::factory()->create();
        $mitarbeiter = $this->mitarbeiter();

        $this->actingAs($mitarbeiter);
        Filament::setCurrentPanel('admin');

        // Die Auswahlliste zeigt ihn gar nicht erst an; geprüft wird hier der
        // Weg daran vorbei — ein abgeschickter Wert, den niemand angeboten
        // hat. Es greift schon die Prüfung des Auswahlfeldes, weshalb hier
        // ein Formularfehler steht und keine Ausnahme. Dahinter liegt in der
        // Aktion noch ein findOrFail über den sichtbarFuer-Scope; beides
        // zusammen ist Absicht, denn die erste Schranke ist die von Filament
        // und die zweite die eigene.
        Livewire::test(InterneNachrichten::class)
            ->callAction('mitKunde', [
                'customer_id' => $fremderKunde->getKey(),
                'text' => 'Darf hier nicht landen',
            ])
            ->assertHasActionErrors(['customer_id']);

        $this->assertDatabaseMissing('nachrichten', ['text' => 'Darf hier nicht landen']);
    }

    public function test_die_eigene_schranke_haelt_auch_ohne_das_auswahlfeld(): void
    {
        // Dieselbe Grenze eine Ebene tiefer: ohne Formular, direkt an der
        // Zuständigkeit. Sie ist die, die bleibt, wenn das Auswahlfeld einmal
        // anders gebaut wird.
        $fremderKunde = Customer::factory()->create();
        $eigenerKunde = Customer::factory()->create();

        $mitarbeiter = $this->mitarbeiter();
        $mitarbeiter->customers()->attach($eigenerKunde);

        $this->assertTrue($mitarbeiter->istBerechtigtFuerKunde($eigenerKunde->getKey()));
        $this->assertFalse($mitarbeiter->istBerechtigtFuerKunde($fremderKunde->getKey()));

        $verlauf = $this->verlaufMit($fremderKunde, $this->admin());
        $this->assertFalse($mitarbeiter->can('schreiben', $verlauf));
    }

    public function test_in_einen_fremden_verlauf_laesst_sich_nicht_schreiben(): void
    {
        // Der Fall, den die Oberfläche nicht hergibt: eine fremde Nummer in
        // der Livewire-Eigenschaft. Ohne die Prüfung in senden() wäre die
        // ganze Zuordnung eine Frage der Anzeige.
        $fremderKunde = Customer::factory()->create();
        $verlauf = $this->verlaufMit($fremderKunde, $this->admin());

        $aussenstehender = $this->mitarbeiter();

        $this->actingAs($aussenstehender);
        Filament::setCurrentPanel('admin');

        Livewire::test(InterneNachrichten::class)
            ->set('unterhaltung', $verlauf->getKey())
            ->set('entwurf', 'Darf hier nicht landen')
            ->call('senden');

        $this->assertDatabaseMissing('nachrichten', ['text' => 'Darf hier nicht landen']);
    }

    public function test_leere_nachricht_wird_nicht_gespeichert(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kunde($kunde);

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(KundenNachrichten::class)
            ->set('entwurf', "   \n  ")
            ->call('senden');

        $this->assertSame(0, Nachricht::query()->count());
    }

    public function test_kundenseite_zeigt_den_verlauf_und_nicht_den_eines_anderen(): void
    {
        $meiner = Customer::factory()->create();
        $fremder = Customer::factory()->create();

        $zugang = $this->kunde($meiner);
        $admin = $this->admin();

        $this->verlaufMit($meiner, $admin, 'Antwort für meinen Kunden');
        $this->verlaufMit($fremder, $admin, 'Antwort für einen anderen');

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(KundenNachrichten::class)
            ->assertOk()
            ->assertSee('Antwort für meinen Kunden')
            ->assertDontSee('Antwort für einen anderen');
    }
}
