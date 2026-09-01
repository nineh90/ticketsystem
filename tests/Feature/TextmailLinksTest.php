<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Links in der Nur-Text-Fassung unserer Mails.
 *
 * Am 01.09.2026 gefunden: alle drei Textmails gaben ihre Adresse mit {{ }}
 * aus. Das maskiert HTML — aus dem "&" vor "signature" wird "&amp;", und der
 * Aufruf kommt ohne Signatur an. Laravel weist ihn mit 403 ab.
 *
 * Bemerkt hat es nie jemand, weil die HTML-Fassung danebenliegt und
 * einwandfrei funktioniert; die meisten Programme zeigen sie an. Wer sein
 * Programm auf Text stellt, kam schlicht nicht durch — und bei der
 * Adressbestätigung ist das die ganze Mail: ohne den Klick geht an diesen
 * Kunden nie wieder etwas hinaus. Von fünf Kundenzugängen hatte an dem Tag
 * kein einziger eine bestätigte Adresse.
 *
 * Der Test prüft deshalb nicht "sieht gut aus", sondern das eine Zeichen,
 * an dem es hing.
 */
class TextmailLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_die_adressbestaetigung_traegt_einen_ganzen_link(): void
    {
        $nutzer = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => Customer::factory()->create()->getKey(),
        ]);

        $url = URL::temporarySignedRoute(
            'kunde.benachrichtigungen.bestaetigen',
            now()->addDays(3),
            ['nutzer' => $nutzer->getKey(), 'pruefsumme' => 'pruefsumme'],
        );

        // Die Voraussetzung des Tests: ohne "&" in der Adresse prüfte er
        // nichts. Signierte Routen haben immer mindestens expires und
        // signature — wenn das eines Tages nicht mehr stimmt, soll der Test
        // das sagen und nicht still durchlaufen.
        $this->assertStringContainsString('&', $url);

        // Geprüft wird die Ansicht und nicht die Mailable: die Mailable
        // rendert beide Fassungen, und die HTML-Fassung war nie kaputt. Hier
        // geht es ausschließlich um die Textfassung.
        $text = view('mail.adressbestaetigung-text', [
            'empfaenger' => $nutzer,
            'url' => $url,
        ])->render();

        $this->assertStringNotContainsString('&amp;', $text);
        $this->assertStringContainsString($url, $text);
    }

    public function test_die_passwortmail_traegt_einen_ganzen_link(): void
    {
        $url = URL::temporarySignedRoute(
            'kunde.benachrichtigungen.bestaetigen',
            now()->addHour(),
            ['nutzer' => 1, 'pruefsumme' => 'pruefsumme'],
        );

        $text = view('mail.passwort-zuruecksetzen-text', [
            'url' => $url,
            'vorname' => 'Sarah',
            'siezen' => true,
            'minuten' => 60,
        ])->render();

        $this->assertStringNotContainsString('&amp;', $text);
        $this->assertStringContainsString($url, $text);
    }

    public function test_die_meldungsmail_traegt_einen_ganzen_link(): void
    {
        $url = URL::temporarySignedRoute(
            'kunde.benachrichtigungen.bestaetigen',
            now()->addHour(),
            ['nutzer' => 1, 'pruefsumme' => 'pruefsumme'],
        );

        $text = view('mail.glockenmeldung-text', [
            'titel' => 'Eine Meldung',
            'text' => 'Der Text der Meldung.',
            'url' => $url,
            'fuerKunden' => true,
        ])->render();

        $this->assertStringNotContainsString('&amp;', $text);
        $this->assertStringContainsString($url, $text);
    }
}
