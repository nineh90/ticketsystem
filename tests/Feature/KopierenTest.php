<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Titel und Beschreibung mit einem Klick in die Zwischenablage (NID-38).
 *
 * Die Zwischenablage selbst lässt sich hier nicht prüfen — sie gehört dem
 * Browser. Geprüft wird, was er dafür bekommt: der richtige Text, und zwar
 * so maskiert, dass ein Anführungszeichen den Ausdruck nicht beendet.
 */
class KopierenTest extends TestCase
{
    use RefreshDatabase;

    private function ansehen(Ticket $ticket)
    {
        $this->actingAs(User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]));
        Filament::setCurrentPanel('admin');

        return Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()]);
    }

    public function test_titel_und_beschreibung_lassen_sich_kopieren(): void
    {
        $ticket = Ticket::factory()->create([
            'titel' => 'Formulare',
            'beschreibung' => "Erster Absatz mit \"Zitat\" und </script>.\n\nZweiter Absatz.",
        ]);

        $seite = $this->ansehen($ticket)
            ->assertSee('Titel kopieren')
            ->assertSee('Kopieren');

        $html = $seite->html();

        // Der Titel so, wie er oben auf der Seite steht.
        $this->assertStringContainsString("writeText('".$ticket->kennung()." — Formulare')", $html);

        // Die Beschreibung vollständig, Anführungszeichen und Script-Tag
        // maskiert — roh hätten sie den Klickhandler beendet.
        $this->assertStringContainsString('\\u0022Zitat\\u0022 und \\u003C\\/script\\u003E.\\n\\nZweiter Absatz.', $html);
        $this->assertStringNotContainsString('und </script>', $html);
    }

    public function test_ohne_beschreibung_gibt_es_nichts_zu_kopieren(): void
    {
        $ticket = Ticket::factory()->create(['beschreibung' => null]);

        $html = $this->ansehen($ticket)->assertSee('Titel kopieren')->html();

        // Nur der Titel-Knopf, kein zweiter für eine leere Beschreibung.
        $this->assertSame(1, substr_count($html, 'clipboard.writeText'));
    }
}
