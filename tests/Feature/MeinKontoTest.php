<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Kunde\Pages\Profil;
use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Mein Konto" im Kundenbereich.
 *
 * Der Schwerpunkt liegt auf dem Moduswechsel — und zwar deshalb, weil er
 * beim ersten Versuch nicht funktioniert hat: die Eigenschaft war gesetzt,
 * die Seite zeigte trotzdem weiter die Ansicht, weil Filament das einmal
 * gebaute Schema für die Dauer der Anfrage festhält. Ein Test, der nur die
 * Eigenschaft prüft, wäre grün gewesen und der Knopf trotzdem tot.
 */
class MeinKontoTest extends TestCase
{
    use RefreshDatabase;

    private function zugang(bool $mussWechseln = false, bool $stammdaten = true): User
    {
        $kunde = Customer::factory()->create(['name' => 'Beispiel GmbH']);

        $nutzer = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
            'passwort_wechseln' => $mussWechseln,
            'stammdaten_pflegen' => $stammdaten,
        ]);

        $this->actingAs($nutzer, 'kunde');
        Filament::setCurrentPanel('kunde');

        return $nutzer;
    }

    public function test_die_seite_beginnt_im_anzeigemodus(): void
    {
        $nutzer = $this->zugang();

        Livewire::test(Profil::class)
            ->assertSuccessful()
            ->assertSet('bearbeiten', false)
            ->assertSee('Beispiel GmbH')
            ->assertSee($nutzer->email)
            // Kein Formular: die Abschnitte, die es nur beim Bearbeiten
            // gibt, dürfen hier nicht stehen.
            ->assertDontSee('Passwort ändern')
            ->assertDontSee('Leer lassen, wenn Ihr Passwort so bleiben soll.');
    }

    public function test_bearbeiten_zeigt_das_formular(): void
    {
        $this->zugang();

        Livewire::test(Profil::class)
            ->callAction('bearbeiten')
            ->assertSet('bearbeiten', true)
            // Das eigentliche Kriterium: die Seite muss das Formular
            // ausliefern, nicht bloß die Eigenschaft umgestellt haben.
            ->assertSee('Passwort ändern')
            ->assertSee('Straße und Hausnummer');
    }

    public function test_abbrechen_fuehrt_zurueck_in_die_ansicht(): void
    {
        $this->zugang();

        // "abbrechen" steht unter dem Formular und nicht im Seitenkopf —
        // als Schema-Aktion muss der Test sie auch so adressieren, sonst
        // sucht er sie unter den Seitenaktionen und findet nichts.
        Livewire::test(Profil::class)
            ->callAction('bearbeiten')
            ->assertSet('bearbeiten', true)
            ->callAction(TestAction::make('abbrechen')->schemaComponent('form-actions', 'content'))
            ->assertSet('bearbeiten', false)
            ->assertDontSee('Passwort ändern');
    }

    public function test_der_kunde_kann_seine_anschrift_weiterhin_aendern(): void
    {
        // Anzeigemodus ist eine Hürde vor dem Formular, kein Verbot: die
        // Daten gehören ihm.
        $nutzer = $this->zugang();

        Livewire::test(Profil::class)
            ->callAction('bearbeiten')
            ->fillForm([
                'kunde_strasse' => 'Musterweg 3',
                'kunde_plz' => '48653',
                'kunde_ort' => 'Coesfeld',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $nutzer->customer->refresh();

        $this->assertSame('Musterweg 3', $nutzer->customer->strasse);
        $this->assertSame('Coesfeld', $nutzer->customer->ort);
    }

    public function test_nach_dem_speichern_steht_wieder_die_ansicht_da(): void
    {
        $this->zugang();

        Livewire::test(Profil::class)
            ->callAction('bearbeiten')
            ->fillForm(['kunde_ort' => 'Münster'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('bearbeiten', false);
    }

    public function test_beim_zugeteilten_passwort_gibt_es_keinen_bearbeiten_knopf(): void
    {
        // Dort ist das Formular der Zweck der Seite — ein Knopf davor wäre
        // eine Hürde vor der Hürde.
        $this->zugang(mussWechseln: true);

        Livewire::test(Profil::class)
            ->assertSuccessful()
            ->assertActionDoesNotExist('bearbeiten')
            ->assertSee('Neues Passwort');
    }

    public function test_ohne_stammdatenrecht_bleibt_die_ansicht_vollstaendig(): void
    {
        // Nachsehen darf jeder Zugang — nur ändern nicht. Sonst müsste er
        // anrufen, um zu erfahren, ob die Anschrift stimmt.
        $nutzer = $this->zugang(stammdaten: false);
        $nutzer->customer->update(['ort' => 'Coesfeld']);

        Livewire::test(Profil::class)
            ->assertSuccessful()
            ->assertSee('Coesfeld')
            ->assertSee('Beispiel GmbH');
    }
}
