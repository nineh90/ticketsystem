<?php

namespace Tests\Feature;

use App\Enums\Erinnerung;
use App\Enums\Rolle;
use App\Mail\Glockenmeldung;
use App\Models\Customer;
use App\Models\Treffen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Die Erinnerung vor einem Treffen.
 *
 * Der Anlass steht in der Sache selbst: ein Termin meldete sich nur, wenn
 * jemand ihn anfasste — angelegt, verschoben, abgesagt. Danach war er still,
 * und bei einem internen Termin heißt das: der Einzige, der dabei ist, hat
 * ihn selbst angesetzt und hört nie wieder davon.
 *
 * Zwei Dinge können hier schiefgehen, und dieser Test hält beide fest:
 *
 *  - Es kommt **nichts** an, weil der Kreis leer war (keine Crew eingetragen)
 *    oder weil der Stempel schon stand.
 *  - Es kommt **zu viel** an: zweimal dieselbe Erinnerung, oder eine an einen
 *    Kunden, der gar nicht eingeladen ist.
 */
class TerminerinnerungTest extends TestCase
{
    use RefreshDatabase;

    /** Ein fester Zeitpunkt — sonst hängen "morgen" und "in einer Stunde" an der Uhr des Rechners. */
    private Carbon $jetzt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jetzt = Carbon::parse('2026-08-20 09:00:00');
        Carbon::setTestNow($this->jetzt);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function intern(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);
    }

    private function kundenzugang(Customer $kunde): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
        ]);
    }

    /**
     * Ein Termin, der lange genug vorher angesetzt wurde — sonst hakt der
     * Planer ihn wortlos ab (Erinnerung::lohntSich).
     */
    private function treffen(array $daten = []): Treffen
    {
        return Treffen::factory()->create(array_merge([
            'beginnt_am' => $this->jetzt->copy()->addDay(),
            'created_at' => $this->jetzt->copy()->subDays(3),
        ], $daten));
    }

    private function titelDerMeldung(User $nutzer): ?string
    {
        return $nutzer->notifications()->first()?->data['title'] ?? null;
    }

    // ------------------------------------------------------------ Die Stufen

    public function test_einen_tag_vorher_meldet_sich_der_termin_bei_der_crew(): void
    {
        $treffen = $this->treffen();
        $kevin = $this->intern();
        $treffen->crew()->attach($kevin);

        $this->artisan('messe:erinnern')->assertSuccessful();

        $this->assertSame(1, $kevin->unreadNotifications()->count());
        $this->assertStringStartsWith('Morgen: ', (string) $this->titelDerMeldung($kevin));
        $this->assertNotNull($treffen->fresh()->erinnert_24h_at);
    }

    public function test_eine_stunde_vorher_kommt_der_weckruf(): void
    {
        $treffen = $this->treffen([
            'beginnt_am' => $this->jetzt->copy()->addMinutes(59),
        ]);
        $kevin = $this->intern();
        $treffen->crew()->attach($kevin);

        $this->artisan('messe:erinnern')->assertSuccessful();

        // Beide Stufen sind fällig, verschickt wird nur die kürzere: ein
        // "Morgen" eine Minute vor dem "Gleich" wäre schlicht falsch.
        $this->assertSame(1, $kevin->unreadNotifications()->count());
        $this->assertStringStartsWith('Gleich: ', (string) $this->titelDerMeldung($kevin));

        $frisch = $treffen->fresh();
        $this->assertNotNull($frisch->erinnert_1h_at);
        $this->assertNotNull($frisch->erinnert_24h_at, 'Die übersprungene Stufe muss trotzdem abgehakt sein.');
    }

    public function test_der_zweite_lauf_meldet_nichts_mehr(): void
    {
        $treffen = $this->treffen();
        $kevin = $this->intern();
        $treffen->crew()->attach($kevin);

        $this->artisan('messe:erinnern');
        $this->artisan('messe:erinnern');

        $this->assertSame(1, $kevin->unreadNotifications()->count(), 'Die Erinnerung ging doppelt raus.');
    }

    public function test_kurzfristig_angesetzte_treffen_werden_nur_abgehakt(): void
    {
        // Wer um kurz vor neun einen Termin für zehn Uhr ansetzt, weiß von
        // ihm — und die Crew hat ihre Einladung im selben Moment bekommen.
        $treffen = $this->treffen([
            'beginnt_am' => $this->jetzt->copy()->addMinutes(50),
            'created_at' => $this->jetzt->copy()->subMinutes(5),
        ]);
        $kevin = $this->intern();
        $treffen->crew()->attach($kevin);

        $this->artisan('messe:erinnern');

        $this->assertSame(0, $kevin->unreadNotifications()->count());
        $this->assertNotNull($treffen->fresh()->erinnert_1h_at);
    }

    public function test_abgesagte_treffen_erinnern_an_nichts(): void
    {
        $treffen = $this->treffen(['abgesagt_at' => $this->jetzt->copy()->subHour()]);
        $kevin = $this->intern();
        $treffen->crew()->attach($kevin);

        $this->artisan('messe:erinnern');

        $this->assertSame(0, $kevin->unreadNotifications()->count());
        $this->assertNull($treffen->fresh()->erinnert_24h_at);
    }

    public function test_vergangene_treffen_erinnern_an_nichts(): void
    {
        $treffen = $this->treffen(['beginnt_am' => $this->jetzt->copy()->subMinutes(10)]);
        $kevin = $this->intern();
        $treffen->crew()->attach($kevin);

        $this->artisan('messe:erinnern');

        $this->assertSame(0, $kevin->unreadNotifications()->count());
    }

    // ------------------------------------------------------------ Der Kreis

    public function test_ohne_crew_geht_die_erinnerung_an_die_person_die_ihn_angesetzt_hat(): void
    {
        // Der Fall, mit dem alles anfing: ein interner Termin, den jemand für
        // sich selbst anlegt, ohne sich in die Crew einzutragen.
        $nils = $this->intern();

        $this->treffen([
            'customer_id' => null,
            'erstellt_von' => $nils->getKey(),
        ]);

        $this->artisan('messe:erinnern');

        $this->assertSame(1, $nils->unreadNotifications()->count());
    }

    public function test_der_kunde_wird_nur_erinnert_wenn_er_eingeladen_ist(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $this->treffen(['customer_id' => $kunde->getKey(), 'kunden_sichtbar' => false]);

        $this->artisan('messe:erinnern');

        $this->assertSame(0, $zugang->unreadNotifications()->count());
    }

    public function test_der_eingeladene_kunde_wird_erinnert(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kundenzugang($kunde);

        $this->treffen(['customer_id' => $kunde->getKey(), 'kunden_sichtbar' => true]);

        // Die Einladung ist beim Anlegen schon rausgegangen (TreffenObserver).
        // Hier geht es um das, was danach kommt.
        $zugang->notifications()->delete();

        $this->artisan('messe:erinnern');

        $this->assertSame(1, $zugang->unreadNotifications()->count());
        $this->assertStringStartsWith('Morgen: ', (string) $this->titelDerMeldung($zugang));
    }

    // ------------------------------------------------------------ Drumherum

    public function test_verschieben_setzt_die_erinnerungen_zurueck(): void
    {
        // Sonst gölte ein Termin, der von heute auf nächste Woche wandert,
        // für immer als erinnert.
        $treffen = $this->treffen();
        $kevin = $this->intern();
        $treffen->crew()->attach($kevin);

        $this->artisan('messe:erinnern');
        $this->assertNotNull($treffen->fresh()->erinnert_24h_at);

        $treffen->update(['beginnt_am' => $this->jetzt->copy()->addWeek()]);

        $this->assertNull($treffen->fresh()->erinnert_24h_at);
        $this->assertNull($treffen->fresh()->erinnert_1h_at);
    }

    public function test_die_erinnerung_kommt_auch_per_mail(): void
    {
        Mail::fake();

        $treffen = $this->treffen();

        $kevin = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
            'mail_benachrichtigungen' => true,
        ]);

        $treffen->crew()->attach($kevin);

        $this->artisan('messe:erinnern');

        Mail::assertSent(
            Glockenmeldung::class,
            fn (Glockenmeldung $mail) => $mail->hasTo($kevin->email)
                && str_starts_with($mail->titel, 'Morgen: '),
        );
    }

    public function test_die_stufen_kennen_ihre_spalten(): void
    {
        // Hängt an der Migration: ein Tippfehler hier fiele sonst erst auf,
        // wenn eine Erinnerung zweimal rausgeht.
        foreach (Erinnerung::cases() as $stufe) {
            $this->assertTrue(
                Schema::hasColumn('treffen', $stufe->spalte()),
                'Die Spalte '.$stufe->spalte().' fehlt.',
            );
        }
    }
}
