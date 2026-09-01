<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Mail\Einladung;
use App\Models\Customer;
use App\Models\User;
use App\Support\Einladen;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die Einladung an einen neuen Kundenzugang.
 *
 * Seit dem 01.09.2026 der Regelweg: Zugang anlegen, Kunde bekommt eine Mail,
 * Kunde vergibt sich selbst ein Passwort. Davor ging das erste Passwort durch
 * einen menschlichen Kanal — mit dem Ergebnis, dass zwei von fünf
 * Kundenzugängen nie benutzt wurden.
 *
 * Der wichtigste Test ist der letzte: nach dem Einlösen darf der Kunde NICHT
 * auf die Profilseite geschickt werden. Das Kennzeichen passwort_wechseln
 * bedeutet "dieses Passwort ist auch uns bekannt", und nach einer Einladung
 * stimmt das nicht mehr. Ohne den Zuhörer PasswortSelbstGesetzt bliebe es
 * stehen, und der Kunde würde aufgefordert, ein Passwort zu vergeben, das er
 * gerade vergeben hat — ausgerechnet beim allerersten Besuch.
 */
class EinladungTest extends TestCase
{
    use RefreshDatabase;

    private function kunde(array $eigenschaften = []): User
    {
        return User::factory()->create(array_merge([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'aktiv' => true,
            'customer_id' => Customer::factory()->create()->getKey(),
            'passwort_wechseln' => true,
        ], $eigenschaften));
    }

    public function test_die_einladung_geht_an_die_adresse_des_zugangs(): void
    {
        Mail::fake();

        $kunde = $this->kunde();

        $this->assertTrue(Einladen::schicken($kunde));

        Mail::assertSent(Einladung::class, fn (Einladung $mail) => $mail->hasTo($kunde->email));
    }

    /**
     * Der Link muss in den Kundenbereich führen.
     *
     * Verschickt wird aus dem INTERNEN Panel heraus (Kunden → Zugänge). Ohne
     * das ausdrückliche Umschalten in Einladen trüge die Mail einen Link auf
     * die interne Anmeldung — die den Kunden mit seinem frisch gesetzten,
     * richtigen Passwort abweist.
     */
    public function test_der_link_fuehrt_in_den_kundenbereich(): void
    {
        Mail::fake();

        Filament::setCurrentPanel('admin');

        $kunde = $this->kunde();

        Einladen::schicken($kunde);

        Mail::assertSent(Einladung::class, fn (Einladung $mail) => str_contains($mail->url, '/kunde/password-reset/reset'));

        // Und das laufende Panel steht danach wieder auf dem internen —
        // sonst zeigte die nächste erzeugte Adresse in den Kundenbereich.
        $this->assertSame('admin', Filament::getCurrentPanel()?->getId());
    }

    /**
     * Die Frist stammt aus dem Broker des Kundenpanels.
     *
     * Nicht bloß Konfigurationskosmetik: eine Stunde ist die Frist, nach der
     * eine über Nacht liegengebliebene Einladung tot wäre. Wird der Broker
     * hier eines Tages wieder auf den Standard gestellt, soll das auffallen.
     */
    public function test_die_frist_ist_grosszuegig_bemessen(): void
    {
        $this->assertSame('kunde', Filament::getPanel('kunde')->getAuthPasswordBroker());
        $this->assertGreaterThanOrEqual(1440, (int) config('auth.passwords.kunde.expire'));
    }

    /**
     * Ohne Adresse geht nichts hinaus — und zwar ohne Ausnahme.
     *
     * Die Spalte email ist NOT NULL, ein solcher Zugang lässt sich also gar
     * nicht speichern; der Test arbeitet deshalb mit einem nicht gespeicherten
     * Objekt. Die Prüfung in Einladen bleibt trotzdem stehen: Mail::to(null)
     * wirft, und diese Ausnahme käme mitten im Anlegen eines Zugangs heraus —
     * also an der Stelle, an der es so aussähe, als sei das Anlegen selbst
     * gescheitert.
     */
    public function test_ohne_adresse_wird_nichts_verschickt(): void
    {
        Mail::fake();

        $kunde = User::factory()->make(['email' => null]);

        $this->assertFalse(Einladen::schicken($kunde));

        Mail::assertNothingSent();
    }

    /**
     * Der Kern: einlösen, und der Wechselzwang ist weg.
     */
    public function test_nach_dem_einloesen_ist_der_wechselzwang_weg(): void
    {
        $kunde = $this->kunde();

        $this->assertTrue($kunde->passwort_wechseln);

        Filament::setCurrentPanel('kunde');

        $token = Password::broker('kunde')->createToken($kunde);

        Livewire::test(ResetPassword::class, ['email' => $kunde->email, 'token' => $token])
            ->fillForm([
                'email' => $kunde->email,
                'password' => 'mein-eigenes-passwort',
                'passwordConfirmation' => 'mein-eigenes-passwort',
            ])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        $kunde->refresh();

        $this->assertTrue(Hash::check('mein-eigenes-passwort', $kunde->password));
        $this->assertFalse(
            $kunde->passwort_wechseln,
            'Nach dem selbst gesetzten Passwort darf der Kunde nicht mehr auf die Profilseite geschickt werden.',
        );
    }

    /**
     * Und die Middleware lässt ihn danach tatsächlich durch.
     *
     * Der Test daneben prüft die Spalte; dieser prüft, was der Kunde erlebt.
     * Beides, weil zwischen "Wert steht richtig in der Datenbank" und "die
     * Umleitung greift nicht mehr" noch die Middleware liegt.
     */
    public function test_er_landet_danach_auf_seiner_uebersicht(): void
    {
        $kunde = $this->kunde(['passwort_wechseln' => false]);

        $this->actingAs($kunde, 'kunde')
            ->get('/kunde')
            ->assertOk()
            ->assertSee('Übersicht', false);
    }

    /**
     * Wer noch ein zugeteiltes Passwort hat, wird weiterhin umgeleitet.
     *
     * Die Gegenprobe zum vorigen Test: der Wechselzwang darf nicht überhaupt
     * verschwunden sein, sondern nur dort, wo der Kunde selbst gewählt hat.
     */
    public function test_ein_zugeteiltes_passwort_fuehrt_weiterhin_zum_profil(): void
    {
        $kunde = $this->kunde(['passwort_wechseln' => true]);

        $this->actingAs($kunde, 'kunde')
            ->get('/kunde')
            ->assertRedirect();
    }
}
