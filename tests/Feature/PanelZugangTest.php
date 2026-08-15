<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die äußere Schranke: ohne Anmeldung kommt niemand ins Panel.
 *
 * In Etappe 3 kommt hier der Fall dazu, dass ein angemeldeter Nutzer OHNE
 * Freigabe (panel_zugang) trotzdem abgewiesen wird — das ist die eigentliche
 * "nur wer freigegeben ist"-Anforderung.
 */
class PanelZugangTest extends TestCase
{
    use RefreshDatabase;

    public function test_gast_wird_auf_login_umgeleitet(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_loginseite_ist_ohne_anmeldung_erreichbar(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_freigegebener_nutzer_erreicht_das_dashboard(): void
    {
        $nutzer = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        $this->actingAs($nutzer)->get('/')->assertOk();
    }

    public function test_konto_ohne_freigabe_kommt_nicht_ins_panel(): void
    {
        // Der eigentliche Punkt der ganzen Konstruktion: ein gültiges Konto
        // mit richtigem Passwort ist noch kein Zugang.
        $nutzer = User::factory()->create(['panel_zugang' => false]);

        $this->actingAs($nutzer)->get('/')->assertForbidden();
    }

    public function test_deaktiviertes_konto_kommt_nicht_ins_panel(): void
    {
        // Ausgeschiedene Mitarbeiter werden deaktiviert statt gelöscht, damit
        // ihre Tickets und Zeitbuchungen zuordenbar bleiben. Die Freigabe
        // allein darf sie dann nicht wieder hereinlassen.
        $nutzer = User::factory()->create([
            'panel_zugang' => true,
            'aktiv' => false,
        ]);

        $this->actingAs($nutzer)->get('/')->assertForbidden();
    }

    public function test_kundenrolle_kommt_nicht_ins_interne_panel(): void
    {
        $nutzer = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
        ]);

        $this->actingAs($nutzer)->get('/')->assertForbidden();
    }

    public function test_sicherheitsheader_werden_ausgeliefert(): void
    {
        // Filament baut seinen Middleware-Stack selbst und durchläuft die
        // "web"-Gruppe nicht. Dieser Test schlägt an, falls die Middleware
        // beim Aufräumen des Panel-Providers aus dessen Liste fliegt.
        $antwort = $this->get('/login');

        $antwort->assertHeader('X-Frame-Options', 'DENY');
        $antwort->assertHeader('X-Content-Type-Options', 'nosniff');
        $antwort->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            $antwort->headers->get('Content-Security-Policy'),
        );
    }

    /**
     * Die Regeln, ohne die das Hochladen von Dateien im Browser abbricht.
     *
     * FilePond — das Upload-Feld hinter jedem Bild- und Dateifeld in
     * Filament — startet einen Web Worker aus einer blob:-URL. Fehlt
     * worker-src, fällt der Browser auf script-src zurück, findet dort kein
     * blob: und blockt still. Der Upload geht dann "einfach nicht", und im
     * Server-Log steht nichts, weil die Datei nie losgeschickt wird.
     *
     * Genau so ist es passiert, und deshalb steht es hier: eine
     * CSP-Verschärfung ist billig geschrieben und teuer zu finden.
     */
    public function test_csp_erlaubt_die_upload_worker(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        foreach (['worker-src', 'child-src', 'connect-src'] as $regel) {
            $this->assertStringContainsString(
                $regel." 'self' blob:",
                $csp,
                'Ohne '.$regel.' mit blob: bricht jeder Datei-Upload im Browser ab.',
            );
        }
    }
}
