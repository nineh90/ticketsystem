<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Resources\Tickets\TicketResource;
use App\Http\Controllers\Api\TicketController;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die Adresse eines Tickets.
 *
 * `/tickets/dlh-3-allergene-pflegen` statt `/tickets/7`. Die Kennung steht
 * vorn und löst allein auf, der Titel dahinter ist Beiwerk — das ist der
 * ganze Trick, und diese Tests halten ihn fest.
 *
 * Sie prüfen vor allem, was schiefgehen könnte, und zwar in der Reihenfolge
 * der Gefahr: dass ein alter Link stirbt, dass ein Titel doppelt vorkommt,
 * dass jemand den Titel ändert.
 */
class TicketAdresseTest extends TestCase
{
    use RefreshDatabase;

    public function test_die_adresse_traegt_kennung_und_titel(): void
    {
        $ticket = $this->ticket('DLH', 'Allergene Pflegen');

        $this->assertSame('dlh-3-allergene-pflegen', $ticket->getRouteKey());
        $this->assertStringEndsWith('/tickets/dlh-3-allergene-pflegen', TicketResource::getUrl('view', ['record' => $ticket]));
    }

    public function test_alte_adressen_mit_der_id_bleiben_gueltig(): void
    {
        // Der Grund, warum das hier steht: in den gespeicherten
        // Benachrichtigungen liegen fertige Adressen wie
        // https://intern.nils-digital.de/tickets/7. Deren "Ansehen"-Knopf
        // muss auch nach der Umstellung noch etwas öffnen — sonst hat die
        // schönere Adresse die alten Meldungen still entwertet.
        $ticket = $this->ticket('DLH', 'Allergene Pflegen');

        $this->actingAs($this->admin())
            ->get('/tickets/'.$ticket->getKey())
            ->assertOk()
            ->assertSee('Allergene Pflegen');
    }

    public function test_derselbe_titel_bei_zwei_kunden_bleibt_unterscheidbar(): void
    {
        // Kein erfundener Fall: "Impressum anpassen" und "Hero überarbeiten"
        // liegen im echten Bestand je zweimal.
        $einer = $this->ticket('KEV', 'Impressum anpassen');
        $anderer = $this->ticket('SAR', 'Impressum anpassen');

        $this->assertNotSame($einer->getRouteKey(), $anderer->getRouteKey());

        $this->actingAs($this->admin());

        $this->get('/tickets/'.$einer->getRouteKey())->assertOk();
        $this->get('/tickets/'.$anderer->getRouteKey())->assertOk();

        // Und beide führen auf das jeweils richtige Ticket, nicht nur
        // irgendwohin.
        $aufloesen = fn (string $adresse) => (new Ticket)->resolveRouteBinding($adresse);

        $this->assertTrue($einer->is($aufloesen($einer->getRouteKey())));
        $this->assertTrue($anderer->is($aufloesen($anderer->getRouteKey())));
    }

    public function test_ein_geaenderter_titel_laesst_den_alten_link_leben(): void
    {
        // Der zweite Grund für die Reihenfolge. Wer einen Link verschickt und
        // danach den Titel schärft, hätte sonst einen toten Link verschickt.
        $ticket = $this->ticket('DLH', 'Allergene Pflegen');
        $alteAdresse = $ticket->getRouteKey();

        $ticket->update(['titel' => 'Allergene doch anders pflegen']);

        $this->actingAs($this->admin())
            ->get('/tickets/'.$alteAdresse)
            ->assertOk()
            ->assertSee('Allergene doch anders pflegen');
    }

    public function test_eine_erfundene_adresse_fuehrt_ins_leere(): void
    {
        $this->ticket('DLH', 'Allergene Pflegen');

        $this->actingAs($this->admin());

        // Kennung, die es nicht gibt.
        $this->get('/tickets/dlh-999-irgendwas')->assertNotFound();
        // Gar keine Kennung.
        $this->get('/tickets/nur-ein-titel-ohne-nummer')->assertNotFound();
    }

    public function test_ein_titel_ohne_buchstaben_laesst_die_kennung_stehen(): void
    {
        // Emoji-Titel gibt es im Bestand. Ohne diesen Fall käme eine Adresse
        // heraus, die auf einen Bindestrich endet.
        $ticket = $this->ticket('DLH', '🎉');

        $this->assertSame('dlh-3', $ticket->getRouteKey());

        $this->actingAs($this->admin())
            ->get('/tickets/'.$ticket->getRouteKey())
            ->assertOk();
    }

    public function test_lange_titel_werden_gekuerzt_und_enden_sauber(): void
    {
        // 250 Zeichen — mehr lässt die Spalte nicht zu, und genau so lange
        // Titel gibt es, wenn jemand eine Mail als Ticket einliefert.
        $ticket = $this->ticket('DLH', str_repeat('sehr langer titel ', 13));

        $adresse = $ticket->getRouteKey();

        $this->assertLessThanOrEqual(70, strlen($adresse));
        $this->assertStringStartsWith('dlh-3-', $adresse);
        $this->assertStringEndsNotWith('-', $adresse);

        $this->actingAs($this->admin())
            ->get('/tickets/'.$adresse)
            ->assertOk();
    }

    public function test_umlaute_werden_lesbar_uebersetzt(): void
    {
        $ticket = $this->ticket('DLH', 'Grüße für Öffnungszeiten');

        $this->assertSame('dlh-3-gruesse-fuer-oeffnungszeiten', $ticket->getRouteKey());
    }

    public function test_die_schnittstelle_liefert_die_sprechende_adresse(): void
    {
        // Was n8n zurückbekommt, wandert in Mails und Chatnachrichten weiter.
        // Dort ist die Adresse oft das Einzige, was jemand vom Ticket sieht,
        // bevor er klickt.
        $ticket = $this->ticket('DLH', 'Allergene Pflegen');

        $antwort = (new \ReflectionClass(TicketController::class))
            ->newInstanceWithoutConstructor();

        $darstellen = new \ReflectionMethod($antwort, 'darstellen');
        $darstellen->setAccessible(true);

        $this->assertStringEndsWith(
            '/tickets/dlh-3-allergene-pflegen',
            $darstellen->invoke($antwort, $ticket)['url'],
        );
    }

    public function test_die_sichtbarkeit_gilt_auch_ueber_die_neue_adresse(): void
    {
        // Eine sprechende Adresse ist auch eine geratene Adresse: wer weiß,
        // dass es KEV gibt, tippt kev-1 einfach hin. Die Rechteprüfung muss
        // hier genauso greifen wie über die ID.
        $ticket = $this->ticket('KEV', 'Impressum anpassen');

        $fremder = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $this->actingAs($fremder)
            ->get('/tickets/'.$ticket->getRouteKey())
            ->assertNotFound();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
    }

    /**
     * Ein Ticket mit vorgegebenem Kürzel und Titel.
     *
     * Der Zähler steht auf 2, damit die Nummer im Test immer 3 ist — sonst
     * müsste jede Erwartung oben die Nummer aus dem Ticket holen und prüfte
     * damit die Adresse gegen sich selbst.
     */
    private function ticket(string $kuerzel, string $titel): Ticket
    {
        $kunde = Customer::factory()->create([
            'kuerzel' => $kuerzel,
            'ticket_zaehler' => 2,
        ]);

        return Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->create([
                'titel' => $titel,
                'ticket_status_id' => TicketStatus::factory()->create()->id,
            ]);
    }
}
