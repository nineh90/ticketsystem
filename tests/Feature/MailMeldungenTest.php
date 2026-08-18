<?php

namespace Tests\Feature;

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

    private function intern(bool $mail, bool $aktiv = true): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
            'aktiv' => $aktiv,
            'mail_benachrichtigungen' => $mail,
        ]);
    }

    /** Eine Meldung durch dieselbe Stelle schicken, durch die alles läuft. */
    private function melden(User ...$an): void
    {
        Benachrichtigung::an(
            collect($an),
            Notification::make()
                ->title('Fehler von Beispiel GmbH')
                ->body('BSP-7 — Bilder laden nicht')
                ->actions([Benachrichtigung::knopf('Ansehen', 'https://intern.nils-digital.de/tickets/7')]),
            'test:1',
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
                && $mail->url === 'https://intern.nils-digital.de/tickets/7';
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
