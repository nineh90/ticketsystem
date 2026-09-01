<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Der Hinweis "das hier ist nicht live".
 *
 * Der eine Test, auf den es ankommt, ist der letzte: der Hinweis darf auf dem
 * Produktivsystem unter keinen Umständen erscheinen. Ein oranger Rahmen um
 * die Seite eines Kunden wäre kein Schönheitsfehler, sondern sähe nach einer
 * Störung aus — und der Text "Kopie der Livedaten" wäre dort schlicht falsch.
 *
 * Die Anmeldeseiten stehen ausdrücklich mit dabei, obwohl sie kein
 * angemeldetes Konto brauchen. Sie sind der Grund, warum es den Hinweis
 * überhaupt gibt: die Verwechslung von lokal und live passiert beim Anmelden,
 * nicht danach.
 */
class LokalHinweisTest extends TestCase
{
    use RefreshDatabase;

    /** Die Umgebung, in der der Hinweis gedacht ist. */
    private function lokal(): void
    {
        app()->detectEnvironment(fn () => 'local');
    }

    public function test_die_kundenanmeldung_zeigt_den_hinweis_lokal(): void
    {
        $this->lokal();

        $this->get('/kunde/login')
            ->assertOk()
            ->assertSee('LOKAL')
            ->assertSee('Kopie der Livedaten');
    }

    public function test_die_interne_anmeldung_zeigt_den_hinweis_lokal(): void
    {
        $this->lokal();

        $this->get('/login')
            ->assertOk()
            ->assertSee('LOKAL');
    }

    public function test_der_kundenbereich_zeigt_den_hinweis_lokal(): void
    {
        $this->lokal();

        $kunde = Customer::factory()->create();

        $nutzer = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
            'passwort_wechseln' => false,
        ]);

        $this->actingAs($nutzer, 'kunde')
            ->get('/kunde')
            ->assertOk()
            ->assertSee('LOKAL');
    }

    /**
     * Der eigentliche Zweck der Datei.
     *
     * Geprüft wird an der Anmeldeseite, weil sie ohne Konto auskommt und
     * denselben Haken durchläuft wie jede andere Seite des Panels
     * (PanelsRenderHook::BODY_START sitzt im gemeinsamen Grundgerüst).
     */
    public function test_auf_dem_produktivsystem_erscheint_er_nirgends(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->get('/kunde/login')
            ->assertOk()
            ->assertDontSee('LOKAL')
            ->assertDontSee('Kopie der Livedaten');

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('LOKAL');
    }
}
