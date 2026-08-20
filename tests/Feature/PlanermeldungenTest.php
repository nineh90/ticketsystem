<?php

namespace Tests\Feature;

use App\Enums\DokumentArt;
use App\Enums\DokumentStand;
use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Dokument;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Wache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Was der Planer von sich aus meldet.
 *
 * Alle diese Zahlen gab es vorher schon — man sah sie nur, wenn man ohnehin
 * hinsah, und das ist bei allem, was liegen bleibt, der unwahrscheinlichste
 * Fall.
 *
 * Zwei Dinge prüft dieser Test deshalb an jeder Meldung: dass sie überhaupt
 * jemanden erreicht, und dass sie **nicht zweimal** kommt. Eine Meldung, die
 * sich wiederholt, schaltet man ab — und dann fehlt sie an dem Tag, an dem
 * sie zählt.
 */
class PlanermeldungenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);
    }

    private function stadium(string $slug = 'offen', array $daten = []): TicketStatus
    {
        return TicketStatus::factory()->create(array_merge(['slug' => $slug, 'name' => 'Offen'], $daten));
    }

    private function ticket(array $daten = [], ?Customer $kunde = null): Ticket
    {
        $kunde ??= Customer::factory()->create();

        return Ticket::factory()
            ->for(Project::factory()->for($kunde, 'customer'), 'project')
            ->for($this->stadium(), 'status')
            ->create(array_merge(['customer_id' => $kunde->getKey()], $daten));
    }

    /** Zeitstempel in die Vergangenheit setzen, ohne dass Eloquent updated_at nachzieht. */
    private function alt(Ticket $ticket, string $wann): void
    {
        DB::table('tickets')->where('id', $ticket->getKey())->update([
            'created_at' => now()->parse($wann),
            'updated_at' => now()->parse($wann),
        ]);
    }

    // ------------------------------------------------------- Morgenmeldung

    public function test_morgens_kommt_was_heute_faellig_ist(): void
    {
        $chef = $this->admin();

        $this->ticket(['assigned_to' => $chef->getKey(), 'faellig_am' => today()]);

        $this->artisan('wache:morgenmeldung')->assertSuccessful();

        $this->assertSame(1, $chef->unreadNotifications()->count());
    }

    public function test_ohne_faelliges_kommt_keine_morgenmeldung(): void
    {
        // Eine Mail, die an manchen Tagen "nichts zu tun" sagt, wischt man
        // nach zwei Wochen ungelesen weg — auch an dem Tag, an dem etwas
        // darin steht.
        $chef = $this->admin();

        $this->ticket(['assigned_to' => $chef->getKey(), 'faellig_am' => today()->addWeek()]);

        $this->artisan('wache:morgenmeldung');

        $this->assertSame(0, $chef->unreadNotifications()->count());
    }

    // ------------------------------------------------------------- Die Uhr

    public function test_abends_wird_an_die_laufende_uhr_erinnert(): void
    {
        $chef = $this->admin();
        $ticket = $this->ticket();

        TimeEntry::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $chef->getKey(),
            'gestartet_am' => now()->subHours(9),
        ]);

        $this->artisan('wache:uhren')->assertSuccessful();

        $this->assertSame(1, $chef->unreadNotifications()->count());
        $this->assertSame('Deine Uhr läuft noch', $chef->notifications()->first()->data['title']);
    }

    public function test_ohne_laufende_uhr_bleibt_es_still(): void
    {
        $chef = $this->admin();

        $this->artisan('wache:uhren');

        $this->assertSame(0, $chef->unreadNotifications()->count());
    }

    // ------------------------------------------------- Liegengebliebenes

    public function test_montags_kommen_die_ruhenden_tickets(): void
    {
        $chef = $this->admin();

        $ticket = $this->ticket(['assigned_to' => $chef->getKey()]);
        $this->alt($ticket, '-'.(Ticket::RUHEND_AB_TAGEN + 1).' days');

        $this->artisan('wache:liegengebliebenes')->assertSuccessful();

        $titel = $chef->notifications()->pluck('data')->pluck('title');

        $this->assertTrue($titel->contains(fn (string $t) => str_contains($t, 'ohne Bewegung')));
    }

    public function test_unzugeteilte_tickets_gehen_an_die_administratoren(): void
    {
        // Ein unzugeteiltes Ticket ist keine vergessene Aufgabe, sondern eine
        // ungetroffene Entscheidung — und die trifft der Administrator.
        $chef = $this->admin();

        $this->ticket(['assigned_to' => null]);

        $this->artisan('wache:liegengebliebenes');

        $titel = $chef->notifications()->pluck('data')->pluck('title');

        $this->assertTrue($titel->contains(fn (string $t) => str_contains($t, 'ohne Zuständige')));
    }

    // --------------------------------------------------- Der wartende Kunde

    public function test_ein_kunde_der_zu_lange_wartet_wird_gemeldet(): void
    {
        $chef = $this->admin();

        $ticket = $this->ticket(['quelle' => Quelle::Kunde]);
        $this->alt($ticket, '-'.(Wache::ANTWORT_SPAETESTENS_STUNDEN + 1).' hours');

        // Das Anliegen selbst hat beim Eingang schon gemeldet (TicketObserver).
        // Hier geht es um das, was einen Tag später passiert.
        $chef->notifications()->delete();

        $this->artisan('wache:kundewartet')->assertSuccessful();

        $this->assertSame(1, $chef->unreadNotifications()->count());
        $this->assertNotNull($ticket->fresh()->nachgehakt_at);
    }

    public function test_gemeldet_wird_nur_einmal(): void
    {
        $chef = $this->admin();

        $ticket = $this->ticket(['quelle' => Quelle::Kunde]);
        $this->alt($ticket, '-2 days');
        $chef->notifications()->delete();

        $this->artisan('wache:kundewartet');
        $this->artisan('wache:kundewartet');

        $this->assertSame(1, $chef->unreadNotifications()->count(), 'Stündlich dieselbe Meldung.');
    }

    public function test_wer_geantwortet_hat_wird_nicht_gemahnt(): void
    {
        $chef = $this->admin();

        $ticket = $this->ticket(['quelle' => Quelle::Kunde]);
        $this->alt($ticket, '-2 days');

        Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $chef->getKey(),
            'body' => 'Schauen wir uns an.',
            'ist_intern' => false,
        ]);

        $chef->notifications()->delete();

        $this->artisan('wache:kundewartet');

        $this->assertSame(0, $chef->unreadNotifications()->count());
    }

    public function test_eine_interne_notiz_ist_keine_antwort(): void
    {
        // Die teuerste Verwechslung: wir hätten das Gefühl, geantwortet zu
        // haben — der Kunde hat davon nichts gesehen.
        $chef = $this->admin();

        $ticket = $this->ticket(['quelle' => Quelle::Kunde]);
        $this->alt($ticket, '-2 days');

        Comment::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $chef->getKey(),
            'body' => 'Kevin soll das machen.',
            'ist_intern' => true,
        ]);

        $chef->notifications()->delete();

        $this->artisan('wache:kundewartet');

        $this->assertSame(1, $chef->unreadNotifications()->count());
    }

    // ---------------------------------------------------------- Die Kasse

    public function test_ueberfaellige_rechnungen_kommen_montags(): void
    {
        $chef = $this->admin();

        Dokument::factory()->create([
            'art' => DokumentArt::Rechnung,
            'stand' => DokumentStand::Offen,
            'faellig_am' => today()->subWeek(),
            'betrag' => 1190.00,
        ]);

        $this->artisan('kasse:fristen')->assertSuccessful();

        $this->assertSame(1, $chef->unreadNotifications()->count());
        $this->assertStringContainsString('überfällige Rechnung', (string) $chef->notifications()->first()->data['body']);
    }

    public function test_ohne_offene_posten_kommt_nichts(): void
    {
        $chef = $this->admin();

        Dokument::factory()->create([
            'art' => DokumentArt::Rechnung,
            'stand' => DokumentStand::Bezahlt,
            'faellig_am' => today()->subWeek(),
        ]);

        $this->artisan('kasse:fristen');

        $this->assertSame(0, $chef->unreadNotifications()->count());
    }

    public function test_zum_monatsanfang_kommt_die_offene_zeit(): void
    {
        $chef = $this->admin();
        $ticket = $this->ticket();

        TimeEntry::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $chef->getKey(),
            'gestartet_am' => now()->subDays(3),
            'beendet_am' => now()->subDays(3)->addHours(2),
            'minuten' => 120,
            'abrechenbar' => true,
        ]);

        $this->artisan('kasse:monat')->assertSuccessful();

        $this->assertSame(1, $chef->unreadNotifications()->count());
        $this->assertStringContainsString('2:00 h', (string) $chef->notifications()->first()->data['title']);
    }
}
