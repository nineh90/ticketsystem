<?php

namespace Tests\Feature;

use App\Enums\Quelle;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-fuer-die-schnittstelle';

    private const TOKEN_WEBSITE = 'test-token-fuer-die-website';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ticketsystem.api_token' => self::TOKEN]);
    }

    /** @param array<string, mixed> $daten */
    private function anlegen(array $daten, ?string $token = self::TOKEN)
    {
        return $this->withHeaders(
            $token === null ? [] : ['Authorization' => 'Bearer '.$token],
        )->postJson('/api/v1/tickets', $daten);
    }

    public function test_ohne_token_kein_zugang(): void
    {
        $this->anlegen(['projekt' => 'x', 'titel' => 'y'], token: null)
            ->assertStatus(401);
    }

    public function test_falscher_token_wird_abgewiesen(): void
    {
        $this->anlegen(['projekt' => 'x', 'titel' => 'y'], token: 'falsch')
            ->assertStatus(401);
    }

    public function test_leerer_token_in_der_konfiguration_sperrt_statt_zu_oeffnen(): void
    {
        // Sonst stünde die Schnittstelle nach einem unvollständigen Deploy
        // für jeden offen.
        config(['ticketsystem.api_token' => '']);

        $this->anlegen(['projekt' => 'x', 'titel' => 'y'])
            ->assertStatus(503);
    }

    public function test_ticket_wird_angelegt(): void
    {
        TicketStatus::factory()->create(['sortierung' => 1]);
        $kunde = Customer::factory()->create(['kuerzel' => 'LDX']);
        $projekt = Project::factory()->for($kunde, 'customer')->create(['slug' => 'website']);

        $antwort = $this->anlegen([
            'projekt' => 'website',
            'titel' => 'Aus einer Mail entstanden',
            'beschreibung' => 'Der Kunde meldet einen Fehler.',
            'prioritaet' => 'hoch',
        ]);

        $antwort->assertStatus(201)
            ->assertJsonPath('neu', true)
            ->assertJsonPath('ticket.kennung', 'LDX-1')
            ->assertJsonPath('ticket.prioritaet', 'hoch');

        $ticket = Ticket::first();
        $this->assertSame($projekt->id, $ticket->project_id);
        $this->assertSame(Quelle::Api, $ticket->quelle);
        // Hinter dem Aufruf steht kein Mensch.
        $this->assertNull($ticket->created_by);
    }

    public function test_projekt_laesst_sich_auch_per_id_angeben(): void
    {
        TicketStatus::factory()->create();
        $projekt = Project::factory()->create();

        $this->anlegen(['projekt' => $projekt->id, 'titel' => 'Per ID'])
            ->assertStatus(201);
    }

    public function test_gleiche_external_ref_legt_kein_zweites_ticket_an(): void
    {
        // Der eigentliche Zweck der Idempotenz: n8n wiederholt bei jedem
        // Zeitüberschreiten, und ohne das entstünden Doppel-Tickets.
        TicketStatus::factory()->create();
        Project::factory()->create(['slug' => 'website']);

        $erste = $this->anlegen([
            'projekt' => 'website',
            'titel' => 'Fehler im Formular',
            'external_ref' => 'mail-abc-123',
        ]);
        $erste->assertStatus(201)->assertJsonPath('neu', true);

        $zweite = $this->anlegen([
            'projekt' => 'website',
            'titel' => 'Fehler im Formular',
            'external_ref' => 'mail-abc-123',
        ]);
        $zweite->assertStatus(200)->assertJsonPath('neu', false);

        $this->assertSame(1, Ticket::count());
        $this->assertSame(
            $erste->json('ticket.id'),
            $zweite->json('ticket.id'),
        );
    }

    public function test_unbekanntes_projekt_wird_erklaert(): void
    {
        TicketStatus::factory()->create();

        $this->anlegen(['projekt' => 'gibtesnicht', 'titel' => 'Test'])
            ->assertStatus(422)
            ->assertJsonStructure(['fehler', 'hinweis']);
    }

    public function test_ohne_stadien_klare_antwort_statt_datenbankfehler(): void
    {
        Project::factory()->create(['slug' => 'website']);

        $this->anlegen(['projekt' => 'website', 'titel' => 'Test'])
            ->assertStatus(503)
            ->assertJsonPath('fehler', 'Es ist kein Ticket-Stadium eingerichtet.');
    }

    public function test_absenderadresse_landet_in_der_beschreibung(): void
    {
        // Ohne sie weiß niemand, wem er antworten soll.
        TicketStatus::factory()->create();
        Project::factory()->create(['slug' => 'website']);

        $this->anlegen([
            'projekt' => 'website',
            'titel' => 'Anfrage',
            'beschreibung' => 'Wann geht es weiter?',
            'absender_email' => 'kunde@example.de',
        ])->assertStatus(201);

        $this->assertStringContainsString('kunde@example.de', Ticket::first()->beschreibung);
        $this->assertStringContainsString('Wann geht es weiter?', Ticket::first()->beschreibung);
        $this->assertSame('kunde@example.de', Ticket::first()->absender_email);
    }

    public function test_adresse_entsteht_aus_app_url_und_nicht_aus_dem_host_des_aufrufs(): void
    {
        // n8n und die Website rufen intern über das Docker-Netz. Mit url()
        // kam https://ticketsystem/tickets/… zurück — das öffnet kein Browser.
        config(['app.url' => 'https://intern.nils-digital.de']);
        TicketStatus::factory()->create();
        $kunde = Customer::factory()->create(['kuerzel' => 'ANF']);
        Project::factory()->for($kunde, 'customer')->create(['slug' => 'anfragen']);

        $daten = ['projekt' => 'anfragen', 'titel' => 'Neue Website', 'external_ref' => 'website-anfrage-1'];
        $kopf = ['Authorization' => 'Bearer '.self::TOKEN];
        $erwartet = 'https://intern.nils-digital.de/tickets/anf-1-neue-website';

        $this->withHeaders($kopf)
            ->postJson('http://ticketsystem/api/v1/tickets', $daten)
            ->assertStatus(201)
            ->assertJsonPath('ticket.url', $erwartet);

        // Auch die Wiederholung, die das bestehende Ticket zurückgibt.
        $this->withHeaders($kopf)
            ->postJson('http://ticketsystem/api/v1/tickets', $daten)
            ->assertStatus(200)
            ->assertJsonPath('ticket.url', $erwartet);
    }

    public function test_ticket_mit_website_token_traegt_die_quelle_website(): void
    {
        config(['ticketsystem.api_token_website' => self::TOKEN_WEBSITE]);
        TicketStatus::factory()->create();
        Project::factory()->create(['slug' => 'anfragen']);

        $this->anlegen(['projekt' => 'anfragen', 'titel' => 'Von der Website'], token: self::TOKEN_WEBSITE)
            ->assertStatus(201);
        $this->anlegen(['projekt' => 'anfragen', 'titel' => 'Von n8n'])
            ->assertStatus(201);

        $this->assertSame(Quelle::Website, Ticket::where('titel', 'Von der Website')->sole()->quelle);
        // Der alte Token gilt weiter und bleibt, was er war.
        $this->assertSame(Quelle::Api, Ticket::where('titel', 'Von n8n')->sole()->quelle);
    }

    public function test_quelle_laesst_sich_nicht_per_feld_im_aufruf_setzen(): void
    {
        TicketStatus::factory()->create();
        Project::factory()->create(['slug' => 'anfragen']);

        $this->anlegen(['projekt' => 'anfragen', 'titel' => 'Getarnt', 'quelle' => 'website'])
            ->assertStatus(201);

        $this->assertSame(Quelle::Api, Ticket::sole()->quelle);
    }

    public function test_website_token_allein_genuegt(): void
    {
        // Wird der n8n-Token widerrufen, darf die Website nicht mit ausfallen.
        config(['ticketsystem.api_token' => '', 'ticketsystem.api_token_website' => self::TOKEN_WEBSITE]);
        TicketStatus::factory()->create();
        Project::factory()->create(['slug' => 'anfragen']);

        $this->anlegen(['projekt' => 'anfragen', 'titel' => 'Test'], token: self::TOKEN_WEBSITE)
            ->assertStatus(201);
        $this->anlegen(['projekt' => 'anfragen', 'titel' => 'Test'], token: self::TOKEN)
            ->assertStatus(401);
    }

    public function test_leerer_website_token_laesst_keinen_leeren_bearer_durch(): void
    {
        // Der Website-Token ist hier nicht gesetzt (Vorgabe ''). Ein leerer
        // oder fehlender Bearer darf deshalb nicht als Treffer zählen.
        $this->anlegen(['projekt' => 'x', 'titel' => 'y'], token: '')
            ->assertStatus(401);
    }

    public function test_absendername_wird_als_feld_gespeichert(): void
    {
        // Damit aus der Anfrage später ein Kontakt wird, ohne dass jemand
        // den Namen aus dem Fließtext abtippt.
        TicketStatus::factory()->create();
        Project::factory()->create(['slug' => 'anfragen']);

        $this->anlegen([
            'projekt' => 'anfragen',
            'titel' => 'Anfrage',
            'beschreibung' => 'Ich brauche eine Website.',
            'absender_name' => 'Erika Muster',
            'absender_email' => 'erika@example.de',
        ])->assertStatus(201);

        $ticket = Ticket::sole();
        $this->assertSame('Erika Muster', $ticket->absender_name);
        $this->assertSame('erika@example.de', $ticket->absender_email);
        $this->assertStringStartsWith('Absender: Erika Muster <erika@example.de>', $ticket->beschreibung);
    }

    public function test_titel_ist_pflicht(): void
    {
        $this->anlegen(['projekt' => 'website'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('titel');
    }

    public function test_projektliste_wird_geliefert(): void
    {
        $kunde = Customer::factory()->create(['name' => 'Lerndex', 'kuerzel' => 'LDX']);
        Project::factory()->for($kunde, 'customer')->create(['slug' => 'website', 'name' => 'Website']);

        $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonPath('projekte.0.slug', 'website')
            ->assertJsonPath('projekte.0.kunde', 'Lerndex')
            ->assertJsonPath('projekte.0.kuerzel', 'LDX');
    }

    public function test_abgeschlossene_projekte_stehen_nicht_in_der_liste(): void
    {
        Project::factory()->create(['slug' => 'laeuft']);
        Project::factory()->create(['slug' => 'fertig', 'status' => 'abgeschlossen']);

        $antwort = $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->getJson('/api/v1/projects');

        $slugs = collect($antwort->json('projekte'))->pluck('slug');

        $this->assertContains('laeuft', $slugs);
        $this->assertNotContains('fertig', $slugs);
    }
}
