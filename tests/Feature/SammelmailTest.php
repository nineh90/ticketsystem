<?php

namespace Tests\Feature;

use App\Enums\MailEreignis;
use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Mail\Glockenmeldung;
use App\Mail\Sammelmeldung;
use App\Models\Customer;
use App\Models\Postausgang;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use App\Support\Sammelmail;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Gesammelte Mails statt einer je Meldung (NID-35).
 *
 * Der Ausgangsfall: ein Kunde stellt in ein paar Minuten fünfzig kleine
 * Anliegen ein, und an der anderen Seite kommen fünfzig Mails an. Diese
 * Tests halten fest, dass daraus eine wird — und dass beim späten Versand
 * nichts hinausgeht, was inzwischen gelesen, abbestellt oder verborgen ist.
 */
class SammelmailTest extends TestCase
{
    use RefreshDatabase;

    private function intern(array $ueberschreiben = []): User
    {
        return User::factory()->create(array_merge([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
            'mail_benachrichtigungen' => true,
        ], $ueberschreiben));
    }

    private function kundenzugang(Customer $kunde): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->id,
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'post@example.org',
            'benachrichtigungs_email_bestaetigt_at' => now(),
        ]);
    }

    private function melden(User $an, string $herkunft, MailEreignis $ereignis = MailEreignis::Anliegen, string $titel = 'Fehler von Beispiel GmbH'): void
    {
        Benachrichtigung::an(
            collect([$an]),
            Notification::make()
                ->title($titel)
                ->actions([Benachrichtigung::knopf('Ansehen', 'http://localhost/'.$herkunft)]),
            $herkunft,
            null,
            $ereignis,
        );

        defer()->invoke();
    }

    public function test_fuenfzig_anliegen_werden_eine_mail(): void
    {
        Mail::fake();

        $nils = $this->intern();

        foreach (range(1, 50) as $nummer) {
            $this->melden($nils, 'test:'.$nummer);
        }

        // Bis zum Leeren geht nichts hinaus — die Glocke hat trotzdem alles.
        Mail::assertNothingSent();
        $this->assertSame(50, $nils->unreadNotifications()->count());

        $this->assertSame(1, Sammelmail::leeren(kunden: false));

        Mail::assertSentCount(1);
        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => $mail->hasTo($nils->email)
            && count($mail->posten) === 50
            && $mail->betreff() === '50 neue Meldungen an Bord');

        // Der Postausgang ist danach leer, ein zweiter Lauf schickt nichts.
        $this->assertSame(0, Postausgang::count());
        $this->assertSame(0, Sammelmail::leeren(kunden: false));
    }

    public function test_eine_einzelne_meldung_traegt_ihren_eigenen_betreff(): void
    {
        Mail::fake();

        $nils = $this->intern();
        $this->melden($nils, 'test:1', titel: 'Wunsch von Beispiel GmbH');

        Sammelmail::leeren(kunden: false);

        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => $mail->betreff() === 'Wunsch von Beispiel GmbH');
    }

    public function test_was_sofort_gehen_soll_geht_sofort(): void
    {
        // Treffen und Angebote warten nicht: das eine hängt an einer Uhrzeit,
        // das andere an Geld.
        Mail::fake();

        $nils = $this->intern();
        $this->melden($nils, 'test:1', MailEreignis::Angebot);

        Mail::assertSent(Glockenmeldung::class);
        $this->assertSame(0, Postausgang::count());
    }

    public function test_schon_gesehenes_kommt_nicht_mehr_per_mail(): void
    {
        Mail::fake();

        $nils = $this->intern();
        $this->melden($nils, 'ticket:1', titel: 'Gesehen');
        $this->melden($nils, 'ticket:2', titel: 'Ungesehen');

        // Ticket 1 hat er inzwischen geöffnet. Die Tickets gibt es hier
        // nicht wirklich — deshalb über test-Herkünfte nachgestellt.
        Postausgang::query()->update(['herkunft' => null]);
        Benachrichtigung::gesehen($nils, 'ticket:1');

        Sammelmail::leeren(kunden: false);

        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => count($mail->posten) === 1
            && $mail->posten[0]['titel'] === 'Ungesehen');
    }

    public function test_ist_alles_gesehen_kommt_gar_keine_mail(): void
    {
        Mail::fake();

        $nils = $this->intern();
        $this->melden($nils, 'test:1');
        $nils->unreadNotifications->markAsRead();

        $this->assertSame(0, Sammelmail::leeren(kunden: false));

        Mail::assertNothingSent();
        $this->assertSame(0, Postausgang::count());
    }

    public function test_aus_der_glocke_geloeschtes_zaehlt_nicht_als_gesehen(): void
    {
        Mail::fake();

        $nils = $this->intern();
        $this->melden($nils, 'test:1');
        $nils->notifications()->delete();

        Sammelmail::leeren(kunden: false);

        Mail::assertSent(Sammelmeldung::class);
    }

    public function test_zum_selben_stand_bleibt_die_letzte_meldung(): void
    {
        Mail::fake();

        $kunde = $this->kundenzugang(Customer::factory()->create());
        $this->melden($kunde, 'test:7', MailEreignis::StandAnKunde, 'Wir brauchen etwas von Ihnen');
        $this->melden($kunde, 'test:7', MailEreignis::StandAnKunde, 'Erledigt');
        $this->melden($kunde, 'test:7', MailEreignis::AntwortAnKunde, 'Neue Antwort');

        Sammelmail::leeren(kunden: true);

        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => array_column($mail->posten, 'titel') === ['Erledigt', 'Neue Antwort']);
    }

    public function test_was_jemand_geschrieben_hat_bleibt_jedes_mal_stehen(): void
    {
        // Drei Notizen unter einem Ticket sind drei Aussagen — die zweite
        // war vielleicht die Frage (NID-36).
        Mail::fake();

        $nils = $this->intern();
        $this->melden($nils, 'test:7', MailEreignis::Kommentar, 'Erste Notiz');
        $this->melden($nils, 'test:7', MailEreignis::Kommentar, 'Zweite Notiz');
        $this->melden($nils, 'test:7', MailEreignis::Antwort, 'Antwort des Kunden');

        Sammelmail::leeren(kunden: false);

        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => count($mail->posten) === 3);
    }

    public function test_wer_inzwischen_abbestellt_bekommt_nichts(): void
    {
        Mail::fake();

        $nils = $this->intern();
        $this->melden($nils, 'test:1');

        $nils->update(['mail_ereignisse' => [MailEreignis::Angebot->value]]);

        $this->assertSame(0, Sammelmail::leeren(kunden: false));
        Mail::assertNothingSent();
    }

    public function test_die_seiten_werden_getrennt_geleert(): void
    {
        Mail::fake();

        $nils = $this->intern();
        $kunde = $this->kundenzugang(Customer::factory()->create());

        $this->melden($nils, 'test:1');
        $this->melden($kunde, 'test:2', MailEreignis::StandAnKunde);

        Sammelmail::leeren(kunden: false);

        Mail::assertSentCount(1);
        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => $mail->hasTo($nils->email));

        // Der Kunde wartet auf seinen eigenen Lauf am Morgen.
        $this->assertSame(1, Postausgang::where('user_id', $kunde->id)->count());

        Sammelmail::leeren(kunden: true);

        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => $mail->hasTo('post@example.org') && $mail->fuerKunden);
    }

    public function test_ein_inzwischen_verborgenes_projekt_steht_nicht_in_der_kundenmail(): void
    {
        Mail::fake();

        $firma = Customer::factory()->create();
        $zugang = $this->kundenzugang($firma);
        $offen = Project::factory()->for($firma, 'customer')->create(['kunden_sichtbar' => true]);
        $verborgen = Project::factory()->for($firma, 'customer')->create(['kunden_sichtbar' => true]);

        $anfang = TicketStatus::factory()->create();
        $erledigt = TicketStatus::factory()->abschluss()->create();
        $a = Ticket::factory()->for($offen, 'project')->create(['ticket_status_id' => $anfang->id]);
        $b = Ticket::factory()->for($verborgen, 'project')->create(['ticket_status_id' => $anfang->id]);

        $a->update(['ticket_status_id' => $erledigt->id]);
        $b->update(['ticket_status_id' => $erledigt->id]);
        defer()->invoke();

        $this->assertSame(2, Postausgang::where('user_id', $zugang->id)->count());

        $verborgen->update(['kunden_sichtbar' => false]);

        Sammelmail::leeren(kunden: true);

        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => count($mail->posten) === 1
            && str_contains($mail->posten[0]['titel'], $a->titel));
    }

    public function test_ein_mitarbeiter_bekommt_nur_was_ihn_angeht(): void
    {
        // Kevin ist einem Kunden zugeordnet. Was ein anderer Kunde meldet,
        // landet bei den Admins, aber nicht in seiner Sammelmail.
        Mail::fake();

        $seiner = Customer::factory()->create(['name' => 'Kevins Kunde']);
        $fremder = Customer::factory()->create(['name' => 'Fremder Kunde']);

        $kevin = $this->intern(['rolle' => Rolle::Mitarbeiter]);
        $kevin->customers()->attach($seiner);
        $nils = $this->intern();

        foreach ([$seiner, $fremder] as $firma) {
            Ticket::factory()->for(Project::factory()->for($firma, 'customer')->create(), 'project')->create([
                'created_by' => $this->kundenzugang($firma)->id,
                'quelle' => Quelle::Kunde,
            ]);
        }
        defer()->invoke();

        Sammelmail::leeren(kunden: false);

        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => $mail->hasTo($kevin->email)
            && array_column($mail->posten, 'kunde') === ['Kevins Kunde']);
        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => $mail->hasTo($nils->email)
            && count($mail->posten) === 2);
    }

    public function test_ein_geloeschtes_ticket_faellt_heraus(): void
    {
        Mail::fake();

        $nils = $this->intern();
        $ticket = Ticket::factory()->create();
        $this->melden($nils, Herkunft::ticket($ticket));
        $ticket->delete();

        $this->assertSame(0, Sammelmail::leeren(kunden: false));
    }

    public function test_intern_faellt_bis_zum_versand_erledigtes_heraus(): void
    {
        Mail::fake();

        $nils = $this->intern();
        $ticket = Ticket::factory()->create(['ticket_status_id' => TicketStatus::factory()->create()->id]);
        $this->melden($nils, Herkunft::ticket($ticket));

        $ticket->update(['ticket_status_id' => TicketStatus::factory()->abschluss()->create()->id]);

        $this->assertSame(0, Sammelmail::leeren(kunden: false));
    }

    public function test_ein_mitarbeiter_bekommt_nichts_was_ein_anderer_hat(): void
    {
        // Kevin ist im Projekt, das Ticket liegt aber bei Nils. Nils als
        // Admin bekommt es trotzdem — er bekäme es auch, läge es bei Kevin.
        Mail::fake();

        $kevin = $this->intern(['rolle' => Rolle::Mitarbeiter]);
        $nils = $this->intern();

        $beiNils = Ticket::factory()->create(['assigned_to' => $nils->id]);
        $offen = Ticket::factory()->create(['assigned_to' => null]);
        $beiKevin = Ticket::factory()->create(['assigned_to' => $kevin->id]);

        foreach ([$beiNils, $offen, $beiKevin] as $ticket) {
            $this->melden($kevin, Herkunft::ticket($ticket), titel: $ticket->kennung());
            $this->melden($nils, Herkunft::ticket($ticket), titel: $ticket->kennung());
        }

        Sammelmail::leeren(kunden: false);

        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => $mail->hasTo($kevin->email)
            && array_column($mail->posten, 'titel') === [$offen->kennung(), $beiKevin->kennung()]);
        Mail::assertSent(Sammelmeldung::class, fn (Sammelmeldung $mail) => $mail->hasTo($nils->email)
            && count($mail->posten) === 3);
    }

    public function test_die_textfassung_escapet_kein_html(): void
    {
        $mail = new Sammelmeldung([
            ['titel' => 'Erledigt: Sozialrecht & Hilfesystem', 'text' => 'KEV-261', 'url' => null, 'farbe' => null, 'kunde' => null],
        ], fuerKunden: true);

        $text = view('mail.sammelmeldung-text', array_merge(
            ['posten' => $mail->posten, 'fuerKunden' => true],
            $mail->content()->with,
        ))->render();

        $this->assertStringContainsString('Sozialrecht & Hilfesystem', $text);
        $this->assertStringNotContainsString('&amp;', $text);
    }
}
