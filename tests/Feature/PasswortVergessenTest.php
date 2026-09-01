<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\PasswortZuruecksetzen;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Passwort vergessen" in beiden Bereichen.
 *
 * Am 01.09.2026 scharf geschaltet, nachdem der Mailversand seit dem 19.08.
 * über Strato läuft. Vorher setzte ein Admin jedes vergessene Passwort von
 * Hand — intern ein Zuruf, für einen Kunden ein Anruf, den er nicht macht.
 *
 * Der Test, auf den es ankommt, ist der zweite: der Link in der Mail muss in
 * den Bereich führen, aus dem heraus er angefordert wurde. Beide Panels
 * teilen sich einen Broker und eine Nutzertabelle; führte der Link eines
 * Kunden auf die interne Anmeldung, käme er dort mit einem frisch gesetzten,
 * richtigen Passwort an — und würde abgewiesen (canAccessPanel). Das sähe für
 * ihn aus, als hätte das Zurücksetzen nicht funktioniert, und es wäre der
 * zweite Weg, auf dem wir ihn genau da verlieren, wo wir ihn abholen wollten.
 */
class PasswortVergessenTest extends TestCase
{
    use RefreshDatabase;

    private function kunde(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => Customer::factory()->create()->getKey(),
        ]);
    }

    public function test_beide_anmeldeseiten_bieten_es_an(): void
    {
        $this->get('/kunde/login')->assertOk()->assertSee('Passwort vergessen');
        $this->get('/login')->assertOk()->assertSee('Passwort vergessen');
    }

    public function test_die_anforderungsseite_ist_in_beiden_bereichen_erreichbar(): void
    {
        $this->get('/kunde/password-reset/request')->assertOk();
        $this->get('/password-reset/request')->assertOk();
    }

    public function test_der_link_eines_kunden_fuehrt_in_den_kundenbereich(): void
    {
        Notification::fake();

        $kunde = $this->kunde();

        Filament::setCurrentPanel('kunde');

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $kunde->email])
            ->call('request')
            ->assertHasNoFormErrors();

        Notification::assertSentTo($kunde, PasswortZuruecksetzen::class, function (PasswortZuruecksetzen $meldung) {
            // Der Pfad, nicht bloß irgendeine URL: "/kunde/" ist genau der
            // Unterschied, an dem die beiden Bereiche auseinandergehen.
            return str_contains($meldung->url, '/kunde/password-reset/reset');
        });
    }

    public function test_der_link_eines_internen_zugangs_fuehrt_nach_innen(): void
    {
        Notification::fake();

        $intern = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        Filament::setCurrentPanel('admin');

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $intern->email])
            ->call('request')
            ->assertHasNoFormErrors();

        Notification::assertSentTo($intern, PasswortZuruecksetzen::class, function (PasswortZuruecksetzen $meldung) {
            return str_contains($meldung->url, '/password-reset/reset')
                && ! str_contains($meldung->url, '/kunde/');
        });
    }

    /**
     * Die Mail darf nicht in die Warteschlange.
     *
     * Der eigentliche Grund, warum es die Klasse PasswortZuruecksetzen gibt.
     * Filaments eigene Meldung ist ein ShouldQueue; bei QUEUE_CONNECTION auf
     * "database" landet sie in der jobs-Tabelle, und einen Worker gibt es in
     * diesem Projekt nirgends — der Container auf dem Server hält
     * ausschließlich "schedule:work" (nachgesehen am 01.09.2026). Die Mail
     * käme also nie an, ohne dass irgendwo ein Fehler stünde. Genau dieselbe
     * Falle steht schon einmal in Benachrichtigung::zustellen beschrieben.
     *
     * In den Tests fällt so etwas nicht auf: dort läuft die Warteschlange auf
     * "sync" und alles sieht richtig aus. Deshalb prüft dieser Test nicht den
     * Versand, sondern die Eigenschaft der Klasse selbst.
     */
    public function test_die_mail_geht_sofort_hinaus_und_nicht_ueber_die_warteschlange(): void
    {
        $meldung = app(FilamentResetPassword::class, ['token' => 'egal']);

        $this->assertInstanceOf(PasswortZuruecksetzen::class, $meldung);
        $this->assertNotInstanceOf(ShouldQueue::class, $meldung);
    }

    /**
     * Eine unbekannte Adresse darf nicht verraten, dass sie unbekannt ist.
     *
     * Filament meldet in beiden Fällen dasselbe. Der Test hält das fest,
     * damit es nicht eines Tages aus Freundlichkeit ("diese Adresse kennen
     * wir nicht") aufgegeben wird — das wäre eine Liste unserer Kunden für
     * jeden, der sie durchprobiert.
     */
    public function test_eine_unbekannte_adresse_verraet_nichts(): void
    {
        Notification::fake();

        Filament::setCurrentPanel('kunde');

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'niemand@example.org'])
            ->call('request')
            ->assertHasNoFormErrors();

        Notification::assertNothingSent();
    }
}
