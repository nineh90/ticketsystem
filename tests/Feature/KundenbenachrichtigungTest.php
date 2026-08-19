<?php

namespace Tests\Feature;

use App\Enums\MailEreignis;
use App\Enums\Rolle;
use App\Filament\Kunde\Pages\Profil;
use App\Filament\Kunde\Widgets\BenachrichtigungenEinrichten;
use App\Mail\Adressbestaetigung as Bestaetigungsmail;
use App\Mail\Glockenmeldung;
use App\Mail\Willkommensmail;
use App\Models\Customer;
use App\Models\User;
use App\Support\Adressbestaetigung;
use App\Support\Benachrichtigung;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Der Weg, auf dem ein Kunde zu Benachrichtigungen kommt.
 *
 * Der Kern ist eine einzige Regel: **ohne bestätigte Adresse geht nichts
 * hinaus.** Alles andere hier prüft, dass diese Regel an keiner Stelle
 * umgangen werden kann — nicht über das Formular, nicht über einen alten
 * Link, nicht über einen Haken, den jemand in der Datenbank setzt.
 */
class KundenbenachrichtigungTest extends TestCase
{
    use RefreshDatabase;

    private function zugang(array $ueberschreiben = []): User
    {
        $nutzer = User::factory()->create(array_merge([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => Customer::factory()->create()->id,
            'passwort_wechseln' => false,
        ], $ueberschreiben));

        $this->actingAs($nutzer, 'kunde');
        Filament::setCurrentPanel('kunde');

        return $nutzer;
    }

    private function melden(User $an, MailEreignis $ereignis = MailEreignis::StandAnKunde): void
    {
        Benachrichtigung::an(
            collect([$an]),
            Notification::make()->title('Erledigt: Bilder tauschen')->body('BSP-3'),
            'test:1',
            null,
            $ereignis,
        );

        defer()->invoke();
    }

    public function test_ohne_bestaetigte_adresse_geht_nichts_hinaus(): void
    {
        // Auch mit gesetztem Haken und eingetragener Adresse. Der Zeitstempel
        // ist die Sperre, nicht der Wille.
        Mail::fake();

        $zugang = $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'kunde@example.org',
        ]);

        $this->assertFalse($zugang->bekommtMailMeldungen());
        $this->melden($zugang);

        Mail::assertNothingSent();
    }

    public function test_mit_bestaetigter_adresse_kommt_die_mail_dorthin(): void
    {
        Mail::fake();

        $zugang = $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'buchhaltung@example.org',
            'benachrichtigungs_email_bestaetigt_at' => now(),
        ]);

        $this->melden($zugang);

        // Ausdrücklich an die genannte Adresse, nicht an die Anmeldeadresse.
        Mail::assertSent(
            Glockenmeldung::class,
            fn (Glockenmeldung $mail) => $mail->hasTo('buchhaltung@example.org')
                && ! $mail->hasTo($zugang->email),
        );
    }

    public function test_der_kunde_traegt_seine_adresse_ein_und_bekommt_einen_link(): void
    {
        Mail::fake();

        $zugang = $this->zugang();

        Livewire::test(Profil::class)
            ->callAction('bearbeiten')
            ->fillForm([
                'mail_benachrichtigungen' => true,
                'benachrichtigungs_email' => 'ich@example.org',
                'mail_ereignisse' => [MailEreignis::StandAnKunde->value],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        defer()->invoke();

        $zugang->refresh();

        $this->assertSame('ich@example.org', $zugang->benachrichtigungs_email);
        $this->assertNull($zugang->benachrichtigungs_email_bestaetigt_at, 'Noch nicht bestätigt.');
        $this->assertNotNull($zugang->benachrichtigungen_gefragt_at, 'Die Frage gilt als gestellt.');

        Mail::assertSent(
            Bestaetigungsmail::class,
            fn (Bestaetigungsmail $mail) => $mail->hasTo('ich@example.org'),
        );

        // Und weiterhin nichts Inhaltliches, solange nicht bestätigt ist.
        $this->melden($zugang);
        Mail::assertNotSent(Glockenmeldung::class);
    }

    public function test_der_link_bestaetigt_die_adresse(): void
    {
        $zugang = $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'ich@example.org',
        ]);

        $this->get(Adressbestaetigung::url($zugang))
            ->assertOk()
            ->assertSee('Adresse bestätigt');

        $this->assertNotNull($zugang->fresh()->benachrichtigungs_email_bestaetigt_at);
        $this->assertTrue($zugang->fresh()->bekommtMailMeldungen());
    }

    public function test_nach_der_bestaetigung_folgt_eine_begruessung(): void
    {
        // Sie ist keine Hoeflichkeit: sie beweist dem Kunden, dass der Weg
        // funktioniert. Ohne sie bleibt es nach dem Klick still, und er weiss
        // bis zum ersten Ereignis nicht, ob es geklappt hat.
        Mail::fake();

        $zugang = $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'ich@example.org',
            'mail_ereignisse' => [MailEreignis::StandAnKunde->value],
        ]);

        $this->get(Adressbestaetigung::url($zugang))->assertOk();
        defer()->invoke();

        Mail::assertSent(
            Willkommensmail::class,
            fn (Willkommensmail $mail) => $mail->hasTo('ich@example.org'),
        );
    }

    public function test_zweimal_klicken_begruesst_nur_einmal(): void
    {
        // Mailprogramme laden Adressen manchmal von sich aus vor.
        Mail::fake();

        $zugang = $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'ich@example.org',
        ]);

        $link = Adressbestaetigung::url($zugang);
        $this->get($link)->assertOk();
        $this->get($link)->assertOk();
        defer()->invoke();

        Mail::assertSent(Willkommensmail::class, 1);
    }

    public function test_wer_zwischendurch_abschaltet_wird_nicht_begruesst(): void
    {
        // Zwischen Anfordern und Klicken kann jemand es sich anders
        // ueberlegt haben — dann waere die Begruessung genau die Mail, die er
        // nicht wollte.
        Mail::fake();

        $zugang = $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'ich@example.org',
        ]);

        $link = Adressbestaetigung::url($zugang);
        $zugang->forceFill(['mail_benachrichtigungen' => false])->save();

        $this->get($link)->assertOk();
        defer()->invoke();

        Mail::assertNotSent(Willkommensmail::class);
    }

    public function test_ein_link_zur_alten_adresse_bestaetigt_die_neue_nicht(): void
    {
        // Der Fall, den man ohne Prüfsumme übersieht: jemand vertippt sich,
        // korrigiert es, und der erste Link bestätigt danach trotzdem.
        $zugang = $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'vertippt@example.org',
        ]);

        $alterLink = Adressbestaetigung::url($zugang);

        $zugang->forceFill(['benachrichtigungs_email' => 'richtig@example.org'])->save();

        $this->get($alterLink)
            ->assertOk()
            ->assertSee('gilt nicht mehr');

        $this->assertNull($zugang->fresh()->benachrichtigungs_email_bestaetigt_at);
    }

    public function test_ein_manipulierter_link_kommt_nicht_durch(): void
    {
        $zugang = $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'ich@example.org',
        ]);

        // Signatur abgeschnitten.
        $this->get(strtok(Adressbestaetigung::url($zugang), '?'))->assertForbidden();

        $this->assertNull($zugang->fresh()->benachrichtigungs_email_bestaetigt_at);
    }

    public function test_eine_geaenderte_adresse_verliert_ihre_bestaetigung(): void
    {
        Mail::fake();

        $zugang = $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'alt@example.org',
            'benachrichtigungs_email_bestaetigt_at' => now(),
            'benachrichtigungen_gefragt_at' => now(),
        ]);

        Livewire::test(Profil::class)
            ->callAction('bearbeiten')
            ->fillForm(['benachrichtigungs_email' => 'neu@example.org'])
            ->call('save')
            ->assertHasNoFormErrors();

        defer()->invoke();

        $this->assertNull(
            $zugang->fresh()->benachrichtigungs_email_bestaetigt_at,
            'Die alte Bestätigung darf für die neue Adresse nicht gelten.',
        );
        Mail::assertSent(Bestaetigungsmail::class);
    }

    public function test_die_frage_verschwindet_sobald_sie_beantwortet_ist(): void
    {
        // Auch bei "nein" — ein Hinweis, der nach der Entscheidung stehen
        // bleibt, ist eine Aufforderung.
        $this->zugang();
        $this->assertTrue(BenachrichtigungenEinrichten::canView());

        $zugang = $this->zugang([
            'mail_benachrichtigungen' => false,
            'benachrichtigungen_gefragt_at' => now(),
        ]);

        $this->assertFalse(BenachrichtigungenEinrichten::canView());
    }

    public function test_bei_ausstehender_bestaetigung_erinnert_die_karte(): void
    {
        $this->zugang([
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'ich@example.org',
            'benachrichtigungen_gefragt_at' => now(),
        ]);

        $this->assertTrue(BenachrichtigungenEinrichten::canView());

        Livewire::test(BenachrichtigungenEinrichten::class)
            ->assertSuccessful()
            ->assertSee('Fast geschafft')
            ->assertSee('ich@example.org');
    }

    public function test_intern_bleibt_alles_wie_es_war(): void
    {
        // Der Umbau darf den internen Versand nicht anfassen: dort gibt es
        // keine zweite Adresse und keine Bestätigung.
        Mail::fake();

        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
            'mail_benachrichtigungen' => true,
        ]);

        $this->assertSame($admin->email, $admin->mailZieladresse());
        $this->assertTrue($admin->bekommtMailMeldungen());

        Benachrichtigung::an(
            collect([$admin]),
            Notification::make()->title('Fehler von Beispiel GmbH'),
            'test:2',
            null,
            MailEreignis::Anliegen,
        );
        defer()->invoke();

        Mail::assertSent(Glockenmeldung::class, fn (Glockenmeldung $m) => $m->hasTo($admin->email));
    }
}
