<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Mail\Wochenmeldung;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\Treffen;
use App\Models\User;
use App\Support\Wache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Montags früh: deine Woche.
 *
 * Der Kern ist das "deine": Kevin steht in Projekten, in denen die meisten
 * Tickets bei Nils liegen, und sieht deren Treffen auf der Brücke. In seine
 * Mail gehört davon nur, was er selbst tun oder wo er selbst sitzen muss.
 */
class WochenmailTest extends TestCase
{
    use RefreshDatabase;

    private Customer $kunde;

    private TicketStatus $offen;

    protected function setUp(): void
    {
        parent::setUp();

        // Montag, 7 Uhr — so, wie der Planer läuft.
        $this->travelTo(now()->next('Monday')->setTime(7, 0));

        $this->kunde = Customer::factory()->create(['name' => 'Beispiel GmbH']);
        $this->offen = TicketStatus::factory()->create();
    }

    private function crew(Rolle $rolle, string $name): User
    {
        $nutzer = User::factory()->create([
            'name' => $name,
            'rolle' => $rolle,
            'panel_zugang' => true,
            'mail_benachrichtigungen' => true,
        ]);

        $nutzer->customers()->attach($this->kunde);

        return $nutzer;
    }

    private function ticket(?User $bei, string $faellig, string $titel): Ticket
    {
        return Ticket::factory()
            ->for(Project::factory()->for($this->kunde, 'customer')->create(), 'project')
            ->create([
                'titel' => $titel,
                'assigned_to' => $bei?->id,
                'faellig_am' => $faellig,
                'ticket_status_id' => $this->offen->id,
            ]);
    }

    private function treffen(User $angesetztVon, string $wann, string $titel, array $crew = []): Treffen
    {
        $treffen = Treffen::factory()->for($this->kunde, 'customer')->create([
            'titel' => $titel,
            'beginnt_am' => $wann,
            'erstellt_von' => $angesetztVon->id,
        ]);

        $treffen->crew()->sync(collect($crew)->pluck('id'));

        return $treffen;
    }

    /** @return array<int, string> */
    private function zeilenAn(User $nutzer): array
    {
        $gefunden = null;

        Mail::assertSent(Wochenmeldung::class, function (Wochenmeldung $mail) use ($nutzer, &$gefunden) {
            if (! $mail->hasTo($nutzer->email)) {
                return false;
            }

            $gefunden = array_column($mail->posten, 'titel');

            return true;
        });

        return $gefunden;
    }

    public function test_jeder_bekommt_nur_seine_tickets_und_seine_treffen(): void
    {
        Mail::fake();

        $nils = $this->crew(Rolle::Admin, 'Nils');
        $kevin = $this->crew(Rolle::Mitarbeiter, 'Kevin');

        $this->ticket($kevin, today()->addDays(2)->toDateString(), 'Kevins Ticket');
        $this->ticket($nils, today()->addDays(2)->toDateString(), 'Nils Ticket');
        $this->ticket(null, today()->addDays(2)->toDateString(), 'Niemandes Ticket');

        $this->treffen($nils, today()->addDay()->setTime(10, 0), 'Call mit Kevin', [$kevin, $nils]);
        $this->treffen($nils, today()->addDays(3)->setTime(14, 0), 'Call ohne Kevin', [$nils]);

        $this->assertSame(2, Wache::wochenplan());

        $kevins = $this->zeilenAn($kevin);
        $this->assertSame(['10:00 Uhr · Treffen: Call mit Kevin', 'Ticket fällig: Kevins Ticket'], $kevins);

        $nils_ = $this->zeilenAn($nils);
        $this->assertContains('14:00 Uhr · Treffen: Call ohne Kevin', $nils_);
        $this->assertContains('Ticket fällig: Nils Ticket', $nils_);
        $this->assertNotContains('Ticket fällig: Kevins Ticket', $nils_);
        $this->assertNotContains('Ticket fällig: Niemandes Ticket', $nils_);
    }

    public function test_ein_treffen_ohne_crew_gehoert_dem_der_es_angesetzt_hat(): void
    {
        Mail::fake();

        $nils = $this->crew(Rolle::Admin, 'Nils');
        $this->treffen($nils, today()->addDay()->setTime(9, 30), 'Kennenlernen');

        Wache::wochenplan();

        $this->assertSame(['09:30 Uhr · Treffen: Kennenlernen'], $this->zeilenAn($nils));
    }

    public function test_ueberfaelliges_steht_obenan(): void
    {
        Mail::fake();

        $kevin = $this->crew(Rolle::Mitarbeiter, 'Kevin');
        $this->ticket($kevin, today()->addDays(4)->toDateString(), 'Diese Woche');
        $this->ticket($kevin, today()->subDays(3)->toDateString(), 'Letzte Woche');

        Wache::wochenplan();

        Mail::assertSent(Wochenmeldung::class, fn (Wochenmeldung $mail) => array_key_first($mail->gruppen) === 'Überfällig'
            && str_ends_with($mail->posten[0]['titel'], 'Letzte Woche')
            && $mail->betreff() === 'Deine Woche: 2 Termine');
    }

    public function test_was_nach_sonntag_liegt_gehoert_in_die_naechste_mail(): void
    {
        Mail::fake();

        $kevin = $this->crew(Rolle::Mitarbeiter, 'Kevin');
        $this->ticket($kevin, today()->addDays(8)->toDateString(), 'Übernächste Woche');

        $this->assertSame(0, Wache::wochenplan());
        Mail::assertNothingSent();
    }

    public function test_ohne_schalter_oder_thema_kommt_nichts(): void
    {
        Mail::fake();

        $aus = $this->crew(Rolle::Mitarbeiter, 'Aus');
        $aus->update(['mail_benachrichtigungen' => false]);
        $ohne = $this->crew(Rolle::Mitarbeiter, 'Ohne');
        $ohne->update(['mail_ereignisse' => ['anliegen']]);

        $this->ticket($aus, today()->addDay()->toDateString(), 'A');
        $this->ticket($ohne, today()->addDay()->toDateString(), 'B');

        $this->assertSame(0, Wache::wochenplan());
    }

    public function test_die_mail_spricht_mit_vornamen_und_nennt_die_treffen(): void
    {
        Mail::fake();

        $kevin = $this->crew(Rolle::Mitarbeiter, 'Kevin Muster');
        $this->ticket($kevin, today()->addDay()->toDateString(), 'Ticket');
        $this->treffen($kevin, today()->addDay()->setTime(11, 0), 'Call', [$kevin]);

        Wache::wochenplan();

        Mail::assertSent(Wochenmeldung::class, fn (Wochenmeldung $mail) => $mail->vorname === 'Kevin'
            && $mail->betreff() === 'Deine Woche: 2 Termine, davon 1 Treffen');
    }
}
