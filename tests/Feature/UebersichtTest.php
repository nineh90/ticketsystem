<?php

namespace Tests\Feature;

use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * GET /api/v1/uebersicht — das Dashboard in der Redaktion der Website.
 *
 * Die Website wird genau gegen diese Form gebaut. Bricht hier ein Test, ist
 * drüben eine Kachel leer.
 */
class UebersichtTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-fuer-die-website';

    private TicketStatus $offen;

    private TicketStatus $erledigt;

    private Project $anfragen;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ticketsystem.api_token_website' => self::TOKEN,
            'app.url' => 'https://intern.nils-digital.de',
        ]);

        // TicketNummerNebenlaeufigTest arbeitet mit DatabaseTruncation und
        // lässt seine acht Tickets stehen — er räumt vor sich auf, nicht
        // hinter sich. Dieser Test zählt aber genau, also beginnt er leer.
        // Die Löschung liegt in der Transaktion von RefreshDatabase.
        Ticket::query()->delete();

        $this->offen = TicketStatus::factory()->create(['name' => 'Backlog', 'sortierung' => 1]);
        $this->erledigt = TicketStatus::factory()->create(['name' => 'Erledigt', 'sortierung' => 2, 'ist_abschluss' => true]);

        $eingang = Customer::factory()->create(['name' => 'Eingang', 'kuerzel' => 'ANF']);
        $this->anfragen = Project::factory()->for($eingang, 'customer')->create(['slug' => 'anfragen']);
    }

    private function abrufen(?string $token = self::TOKEN): TestResponse
    {
        return $this->withHeaders($token === null ? [] : ['Authorization' => 'Bearer '.$token])
            ->getJson('http://ticketsystem/api/v1/uebersicht');
    }

    /** @param array<string, mixed> $daten */
    private function ticket(array $daten = [], ?Project $projekt = null): Ticket
    {
        return Ticket::factory()
            ->for($projekt ?? Project::factory()->create(), 'project')
            ->create(array_merge(['ticket_status_id' => $this->offen->getKey()], $daten));
    }

    private function antwort(Ticket $ticket, bool $intern): void
    {
        Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => User::factory()->create(['rolle' => Rolle::Admin])->getKey(),
            'body' => 'Wir melden uns.',
            'ist_intern' => $intern,
        ]);
    }

    public function test_ohne_token_kein_zugang(): void
    {
        $this->abrufen(token: null)->assertStatus(401);
    }

    public function test_die_form_steht(): void
    {
        $this->ticket(['titel' => 'Neue Website'], $this->anfragen);

        $this->abrufen()
            ->assertOk()
            ->assertJsonStructure([
                'offen', 'wartet_auf_uns', 'anfragen_offen',
                'neueste' => [['kennung', 'titel', 'status', 'kunde', 'url', 'geaendert_am']],
            ])
            ->assertJsonPath('neueste.0.kennung', 'ANF-1')
            ->assertJsonPath('neueste.0.status', 'Backlog')
            ->assertJsonPath('neueste.0.kunde', 'Eingang')
            // Öffentliche Adresse, obwohl intern gerufen wurde.
            ->assertJsonPath('neueste.0.url', 'https://intern.nils-digital.de/tickets/anf-1-neue-website');
    }

    public function test_geaendert_am_traegt_die_zeitzone(): void
    {
        $this->ticket();

        $wert = $this->abrufen()->json('neueste.0.geaendert_am');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $wert);
    }

    public function test_offen_zaehlt_nur_was_nicht_abgeschlossen_ist(): void
    {
        $this->ticket();
        $this->ticket([], $this->anfragen);
        $this->ticket(['ticket_status_id' => $this->erledigt->getKey()], $this->anfragen);

        $this->abrufen()
            ->assertJsonPath('offen', 2)
            ->assertJsonPath('anfragen_offen', 1);
    }

    public function test_wartet_auf_uns_zaehlt_was_von_aussen_kam_und_ohne_antwort_ist(): void
    {
        // Zählt: vom Kunden und von der Website, beide ohne Antwort.
        $this->ticket(['quelle' => Quelle::Kunde]);
        $this->ticket(['quelle' => Quelle::Website], $this->anfragen);

        // Zählt nicht: eigenes Ticket, n8n-Ticket, erledigtes.
        $this->ticket(['quelle' => Quelle::Manuell]);
        $this->ticket(['quelle' => Quelle::Api]);
        $this->ticket(['quelle' => Quelle::Kunde, 'ticket_status_id' => $this->erledigt->getKey()]);

        $this->abrufen()->assertJsonPath('wartet_auf_uns', 2);
    }

    public function test_worauf_wir_geantwortet_haben_zaehlt_nicht_mehr(): void
    {
        $beantwortet = $this->ticket(['quelle' => Quelle::Kunde]);
        $this->antwort($beantwortet, intern: false);

        // Eine interne Notiz sieht draußen niemand — das Ticket wartet weiter.
        $nurNotiz = $this->ticket(['quelle' => Quelle::Website], $this->anfragen);
        $this->antwort($nurNotiz, intern: true);

        $this->abrufen()->assertJsonPath('wartet_auf_uns', 1);
    }

    public function test_dashboard_und_erinnerung_meinen_dieselben_tickets(): void
    {
        // Der Planer mahnt erst nach einem Tag; davon abgesehen muss er
        // genau die Tickets treffen, die die Übersicht zählt.
        User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);

        $wartend = $this->ticket(['quelle' => Quelle::Kunde]);
        $anfrage = $this->ticket(['quelle' => Quelle::Website], $this->anfragen);
        $beantwortet = $this->ticket(['quelle' => Quelle::Kunde]);
        $this->antwort($beantwortet, intern: false);

        DB::table('tickets')->update(['created_at' => now()->subDays(2)]);

        $this->abrufen()->assertJsonPath('wartet_auf_uns', 2);

        $this->artisan('wache:kundewartet')->assertSuccessful();

        $this->assertNotNull($wartend->fresh()->nachgehakt_at);
        $this->assertNotNull($anfrage->fresh()->nachgehakt_at);
        $this->assertNull($beantwortet->fresh()->nachgehakt_at);
    }

    public function test_neueste_sind_hoechstens_zehn_und_die_juengste_steht_vorn(): void
    {
        foreach (range(1, 12) as $nummer) {
            $ticket = $this->ticket(['titel' => 'Ticket '.$nummer]);

            DB::table('tickets')->where('id', $ticket->getKey())
                ->update(['updated_at' => now()->subMinutes(100 - $nummer)]);
        }

        $antwort = $this->abrufen();

        $this->assertCount(10, $antwort->json('neueste'));
        $antwort->assertJsonPath('neueste.0.titel', 'Ticket 12');
    }

    public function test_ohne_tickets_kommt_eine_leere_liste_und_kein_fehler(): void
    {
        $this->abrufen()
            ->assertOk()
            ->assertExactJson(['offen' => 0, 'wartet_auf_uns' => 0, 'anfragen_offen' => 0, 'neueste' => []]);
    }
}
