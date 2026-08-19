<?php

namespace Tests\Feature;

use App\Enums\MailEreignis;
use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Mail\Glockenmeldung;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Support\Benachrichtigung;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Meldungen zusätzlich per Mail.
 *
 * Der Versand wird stufenweise eingeführt — zuerst ein einziger Zugang. Diese
 * Tests halten fest, wer sie bekommt und vor allem, wer nicht: ein
 * Kundenzugang nie, gleich was am Schalter steht. Seine Adresse hat niemand
 * bestätigt, und im Betreff einer Meldung steht schon der halbe Inhalt.
 */
class MailMeldungenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>|null  $ereignisse  null heißt: alles
     */
    private function intern(bool $mail, bool $aktiv = true, ?array $ereignisse = null): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
            'aktiv' => $aktiv,
            'mail_benachrichtigungen' => $mail,
            'mail_ereignisse' => $ereignisse,
        ]);
    }

    /** Eine Meldung durch dieselbe Stelle schicken, durch die alles läuft. */
    private function melden(User $an, MailEreignis $ereignis = MailEreignis::Anliegen): void
    {
        Benachrichtigung::an(
            collect([$an]),
            Notification::make()
                ->title('Fehler von Beispiel GmbH')
                ->body('BSP-7 — Bilder laden nicht')
                ->actions([Benachrichtigung::knopf('Ansehen', 'http://localhost/tickets/bsp-7')]),
            'test:1',
            null,
            $ereignis,
        );

        // Der Versand läuft nach der Antwort. Im Test gibt es keine, also
        // werden die zurückgestellten Aufrufe hier von Hand ausgelöst.
        defer()->invoke();
    }

    public function test_wer_den_schalter_gesetzt_hat_bekommt_die_mail(): void
    {
        Mail::fake();

        $mit = $this->intern(mail: true);
        $this->melden($mit);

        Mail::assertSent(Glockenmeldung::class, function (Glockenmeldung $mail) use ($mit) {
            return $mail->hasTo($mit->email)
                && $mail->titel === 'Fehler von Beispiel GmbH'
                && $mail->text === 'BSP-7 — Bilder laden nicht'
                // Der Knopf aus der Meldung wandert unverändert in die Mail.
                && $mail->url === 'http://localhost/tickets/bsp-7';
        });
    }

    public function test_ohne_schalter_kommt_nichts(): void
    {
        Mail::fake();

        $this->melden($this->intern(mail: false));

        Mail::assertNothingSent();
    }

    public function test_ein_kundenzugang_bekommt_nie_eine_mail(): void
    {
        // Auch mit gesetztem Schalter. Das ist die Sperre, die fällt, wenn
        // der Versand nach außen drankommt — dann aber zusammen mit einer
        // bestätigten Adresse.
        Mail::fake();

        $kunde = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => Customer::factory()->create()->id,
            'mail_benachrichtigungen' => true,
        ]);

        $this->assertFalse($kunde->bekommtMailMeldungen());

        $this->melden($kunde);

        Mail::assertNothingSent();
    }

    public function test_ein_stillgelegter_zugang_bekommt_nichts(): void
    {
        Mail::fake();

        $this->melden($this->intern(mail: true, aktiv: false));

        Mail::assertNothingSent();
    }

    public function test_die_glocke_bleibt_auch_ohne_mailversand_stehen(): void
    {
        // Der wichtigste Fall: ein Mailserver, der nicht antwortet, darf die
        // Meldung nicht mitreißen. Die Glocke ist das Verlässliche, die Mail
        // der Hinweis darauf.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP tot'));

        $nutzer = $this->intern(mail: true);
        $this->melden($nutzer);

        $this->assertSame(1, $nutzer->unreadNotifications()->count());
    }

    public function test_ohne_eigene_auswahl_kommt_alles(): void
    {
        // null heißt "alles" und nicht "nichts". Wer die Auswahl nie
        // angefasst hat, bekommt auch Ereignisse, die es beim Anlegen seines
        // Zugangs noch gar nicht gab.
        $nutzer = $this->intern(mail: true, ereignisse: null);

        foreach (MailEreignis::cases() as $ereignis) {
            $this->assertTrue(
                $nutzer->willMailZu($ereignis),
                $ereignis->value.' müsste ohne eigene Auswahl durchgehen.',
            );
        }
    }

    public function test_die_auswahl_laesst_nur_das_gewaehlte_durch(): void
    {
        Mail::fake();

        $nutzer = $this->intern(mail: true, ereignisse: [MailEreignis::Anliegen->value]);

        $this->melden($nutzer, MailEreignis::Anliegen);
        Mail::assertSentCount(1);

        // Nicht gewählt — und damit auch nicht zugestellt.
        $this->melden($nutzer, MailEreignis::Nachricht);
        Mail::assertSentCount(1);

        // Die Glocke bekommt beides, unabhängig von der Mailauswahl. Sie ist
        // das Verlässliche, die Mail nur der Hinweis darauf.
        $this->assertSame(2, $nutzer->unreadNotifications()->count());
    }

    public function test_eine_leere_auswahl_heisst_wirklich_nichts(): void
    {
        // Anders als null: eine leere Liste entsteht nur, wenn jemand
        // bewusst alle Haken entfernt hat.
        Mail::fake();

        $this->melden($this->intern(mail: true, ereignisse: []), MailEreignis::Anliegen);

        Mail::assertNothingSent();
    }

    public function test_die_vorgabe_umfasst_alles_was_hereinkommt(): void
    {
        $vorgabe = MailEreignis::vorgabeIntern();

        $this->assertContains(MailEreignis::Anliegen->value, $vorgabe);
        $this->assertContains(MailEreignis::Angebot->value, $vorgabe);

        // Was hinausgeht, gehört nicht dazu: über die eigene Antwort braucht
        // niemand eine Mail, er war es meistens selbst.
        $this->assertNotContains(MailEreignis::AntwortAnKunde->value, $vorgabe);
        $this->assertNotContains(MailEreignis::StandAnKunde->value, $vorgabe);
    }

    public function test_ein_kundenzugang_bleibt_auch_mit_auswahl_gesperrt(): void
    {
        Mail::fake();

        $kunde = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => Customer::factory()->create()->id,
            'mail_benachrichtigungen' => true,
            'mail_ereignisse' => [MailEreignis::StandAnKunde->value],
        ]);

        $this->melden($kunde, MailEreignis::StandAnKunde);

        Mail::assertNothingSent();
    }

    public function test_eine_echte_meldung_geht_denselben_weg(): void
    {
        // Nicht nur die Hilfsmethode oben, sondern der Weg, den ein
        // gemeldetes Kundenanliegen tatsächlich nimmt.
        Mail::fake();

        $admin = $this->intern(mail: true);
        $kunde = Customer::factory()->create(['name' => 'Beispiel GmbH']);
        $projekt = Project::factory()->for($kunde, 'customer')->create();

        $melder = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->id,
        ]);

        Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => TicketStatus::factory()->create()->id,
            'created_by' => $melder->id,
            'quelle' => Quelle::Kunde,
        ]);

        defer()->invoke();

        Mail::assertSent(Glockenmeldung::class, fn (Glockenmeldung $mail) => $mail->hasTo($admin->email));
    }
}
