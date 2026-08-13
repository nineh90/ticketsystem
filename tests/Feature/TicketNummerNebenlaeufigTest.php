<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Nummernvergabe unter echter Nebenläufigkeit.
 *
 * Der Test startet mehrere getrennte PHP-Prozesse, die gleichzeitig Tickets
 * für denselben Kunden anlegen. Nur so lässt sich prüfen, ob die Sperre in
 * Ticket::naechsteNummer() etwas taugt — innerhalb eines Prozesses gibt es
 * kein Rennen, das man gewinnen könnte.
 *
 * Deshalb auch DatabaseTruncation statt RefreshDatabase: RefreshDatabase
 * hüllt jeden Test in eine Transaktion, deren Daten außerhalb des eigenen
 * Prozesses unsichtbar sind — die Kindprozesse fänden weder Kunde noch
 * Projekt vor.
 *
 * Ohne die Sperre lesen zwei Prozesse denselben Zählerstand und vergeben
 * dieselbe Nummer; der UNIQUE-Index lässt dann einen von beiden scheitern.
 * Beide Fehlerbilder deckt dieser Test ab.
 */
class TicketNummerNebenlaeufigTest extends TestCase
{
    use DatabaseTruncation;

    private const PARALLEL = 8;

    public function test_parallele_prozesse_bekommen_verschiedene_nummern(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open steht nicht zur Verfügung.');
        }

        $kunde = Customer::factory()->create(['kuerzel' => 'PAR']);
        $projekt = Project::factory()->for($kunde, 'customer')->create();
        $status = TicketStatus::factory()->create();

        $code = sprintf(
            'App\Models\Ticket::create(["project_id" => %d, "titel" => "parallel", "ticket_status_id" => %d]);',
            $projekt->id,
            $status->id,
        );

        $prozesse = [];
        $pipes = [];

        for ($i = 0; $i < self::PARALLEL; $i++) {
            $prozesse[$i] = proc_open(
                [PHP_BINARY, 'artisan', 'tinker', '--execute', $code],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes[$i],
                base_path(),
                $this->umgebung(),
            );

            $this->assertIsResource($prozesse[$i], "Prozess {$i} konnte nicht gestartet werden.");
        }

        $fehler = [];

        foreach ($prozesse as $i => $prozess) {
            $ausgabe = stream_get_contents($pipes[$i][1]).stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);

            if (proc_close($prozess) !== 0) {
                $fehler[] = "Prozess {$i}: ".trim($ausgabe);
            }
        }

        $this->assertSame([], $fehler, "Mindestens ein Prozess ist gescheitert:\n".implode("\n", $fehler));

        $nummern = Ticket::query()
            ->where('customer_id', $kunde->id)
            ->orderBy('nummer')
            ->pluck('nummer')
            ->all();

        $this->assertCount(self::PARALLEL, $nummern, 'Es wurden nicht alle Tickets angelegt.');

        // Lückenlos 1..8 und jede Nummer genau einmal.
        $this->assertSame(range(1, self::PARALLEL), $nummern);

        // Und der Zähler am Kunden steht auf demselben Stand.
        $this->assertSame(self::PARALLEL, (int) $kunde->fresh()->ticket_zaehler);
    }

    /**
     * Umgebung für die Kindprozesse.
     *
     * Sie müssen auf dieselbe Test-Datenbank zeigen wie der Testlauf selbst.
     * Laravel lädt die .env "immutable", überschreibt also keine bereits
     * gesetzten Umgebungsvariablen — die Werte hier gewinnen deshalb gegen
     * die Entwicklungs-.env. Ohne das schriebe der Test in die echte
     * Entwicklungsdatenbank.
     */
    private function umgebung(): array
    {
        return array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => config('database.connections.pgsql.database'),
            'DB_USERNAME' => config('database.connections.pgsql.username'),
            'DB_PASSWORD' => config('database.connections.pgsql.password'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]);
    }
}
