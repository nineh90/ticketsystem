<?php

namespace Tests\Feature;

use App\Enums\MailEreignis;
use App\Enums\Rolle;
use App\Filament\Pages\Kanban;
use App\Filament\Widgets\OffeneFragen;
use App\Livewire\Ticketverlauf;
use App\Mail\Glockenmeldung;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Postausgang;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kommentare, die nicht untergehen (NID-36).
 *
 * Vorher meldete eine interne Notiz niemanden — und fast alle Kommentare
 * sind intern, weil "Interne Notiz" voreingestellt ist. Kevins Fragen an
 * Nils standen an Tickets, die Nils nicht öffnete. Diese Tests halten fest,
 * wer jetzt was erfährt, und dass dabei nichts nach außen dringt.
 */
class TicketgespraechTest extends TestCase
{
    use RefreshDatabase;

    private User $nils;

    private User $kevin;

    private Customer $kunde;

    private User $kundenzugang;

    private Project $projekt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kunde = Customer::factory()->create();
        $this->projekt = Project::factory()->for($this->kunde, 'customer')->create(['kunden_sichtbar' => true]);

        $this->nils = User::factory()->create(['name' => 'Nils', 'rolle' => Rolle::Admin, 'panel_zugang' => true, 'mail_benachrichtigungen' => true]);
        $this->kevin = User::factory()->create(['name' => 'Kevin', 'rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true, 'mail_benachrichtigungen' => true]);
        $this->kevin->customers()->attach($this->kunde);

        $this->kundenzugang = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $this->kunde->id,
            'mail_benachrichtigungen' => true,
            'benachrichtigungs_email' => 'kunde@example.org',
        ]);
        $this->kundenzugang->forceFill(['benachrichtigungs_email_bestaetigt_at' => now()])->save();
    }

    private function ticket(?User $bei = null): Ticket
    {
        return Ticket::factory()->for($this->projekt, 'project')->create([
            'assigned_to' => $bei?->id,
            'ticket_status_id' => TicketStatus::factory()->create()->id,
        ]);
    }

    private function schreiben(User $als, Ticket $ticket, string $text, bool $intern = true, ?User $frageAn = null): void
    {
        Livewire::actingAs($als)
            ->test(Ticketverlauf::class, ['ticket' => $ticket])
            ->set('entwurf', $text)
            ->set('intern', $intern)
            ->set('frageAn', $frageAn?->id)
            ->call('senden')
            ->assertHasNoErrors();

        defer()->invoke();
    }

    public function test_eine_interne_notiz_meldet_sich_beim_team_und_nicht_beim_schreiber(): void
    {
        $ticket = $this->ticket($this->kevin);

        $this->schreiben($this->kevin, $ticket, 'Ich hab das PDF angehalten.');

        $this->assertSame(1, $this->nils->unreadNotifications()->count());
        $this->assertSame(0, $this->kevin->unreadNotifications()->count());
        $this->assertSame(0, $this->kundenzugang->notifications()->count());

        // Per Mail mit der nächsten Sammelmail, nicht sofort.
        $this->assertSame(MailEreignis::Kommentar, Postausgang::sole()->ereignis);
    }

    public function test_eine_notiz_an_einem_fremden_ticket_geht_nicht_an_den_mitarbeiter(): void
    {
        // Kevin ist dem Kunden zugeordnet, das Ticket liegt aber bei Nils.
        $ticket = $this->ticket($this->nils);
        $admin2 = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);

        $this->schreiben($this->nils, $ticket, 'Notiz für mich selbst.');

        $this->assertSame(0, $this->kevin->notifications()->count());
        $this->assertSame(1, $admin2->notifications()->count());
    }

    public function test_eine_frage_geht_sofort_an_den_gefragten(): void
    {
        Mail::fake();
        $ticket = $this->ticket($this->kevin);

        $this->schreiben($this->kevin, $ticket, 'Wohin schicken? Im Text steht kontakt@.', frageAn: $this->nils);

        Mail::assertSent(Glockenmeldung::class, fn (Glockenmeldung $mail) => $mail->hasTo($this->nils->email)
            && $mail->titel === 'Frage von Kevin zu '.$ticket->kennung()
            && $mail->text === 'Wohin schicken? Im Text steht kontakt@.');

        // Nicht zusätzlich noch einmal als Notiz in der Sammelmail.
        $this->assertSame(0, Postausgang::where('user_id', $this->nils->id)->count());
        $this->assertTrue(Comment::sole()->istOffeneFrage());
    }

    public function test_wer_gefragt_war_und_antwortet_hakt_die_frage_ab(): void
    {
        $ticket = $this->ticket($this->kevin);
        $this->schreiben($this->kevin, $ticket, 'Zwei Fragen …', frageAn: $this->nils);

        $frage = Comment::sole();

        // Ein Dritter antwortet — die Frage bleibt offen.
        $this->schreiben(User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]), $ticket, 'Ich weiß es auch nicht.');
        $this->assertTrue($frage->fresh()->istOffeneFrage());

        $this->schreiben($this->nils, $ticket, 'An info@ bitte.');
        $this->assertFalse($frage->fresh()->istOffeneFrage());
    }

    public function test_eine_frage_gibt_es_nur_intern(): void
    {
        $ticket = $this->ticket($this->kevin);

        $this->schreiben($this->kevin, $ticket, 'Für den Kunden sichtbar.', intern: false, frageAn: $this->nils);

        $this->assertNull(Comment::sole()->frage_an);
    }

    public function test_fragen_kann_man_nur_wer_das_ticket_sieht(): void
    {
        $fremder = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);
        $ticket = $this->ticket($this->kevin);

        $this->schreiben($this->kevin, $ticket, 'Frage ins Leere', frageAn: $fremder);

        $this->assertNull(Comment::sole()->frage_an);
        $this->assertSame(0, $fremder->notifications()->count());
    }

    public function test_abhaken_darf_nur_wer_beteiligt_ist(): void
    {
        $ticket = $this->ticket(null);
        $this->schreiben($this->nils, $ticket, 'Kevin, kannst du das übernehmen?', frageAn: $this->kevin);
        $frage = Comment::sole();

        $unbeteiligt = User::factory()->create(['rolle' => Rolle::Mitarbeiter, 'panel_zugang' => true]);
        $unbeteiligt->customers()->attach($this->kunde);

        Livewire::actingAs($unbeteiligt)
            ->test(Ticketverlauf::class, ['ticket' => $ticket])
            ->call('beantwortet', $frage->id)
            ->assertForbidden();

        Livewire::actingAs($this->kevin)
            ->test(Ticketverlauf::class, ['ticket' => $ticket])
            ->call('beantwortet', $frage->id);

        $this->assertFalse($frage->fresh()->istOffeneFrage());
    }

    public function test_ein_kundenzugang_kommt_nicht_an_den_internen_verlauf(): void
    {
        $ticket = $this->ticket();

        Livewire::actingAs($this->kundenzugang)
            ->test(Ticketverlauf::class, ['ticket' => $ticket])
            ->assertForbidden();
    }

    public function test_die_kundenantwort_steht_in_der_meldung_nicht_mehr_abgeschnitten(): void
    {
        $ticket = $this->ticket($this->kevin);
        $text = str_repeat('Lang und ausführlich. ', 15).'Und hier kommt die eigentliche Frage?';

        Comment::create(['ticket_id' => $ticket->id, 'user_id' => $this->kundenzugang->id, 'body' => $text, 'ist_intern' => false]);

        $meldung = $this->kevin->notifications()->sole();
        $this->assertStringContainsString('die eigentliche Frage?', $meldung->data['body']);
    }

    public function test_der_verlauf_steht_aelteste_zuerst_und_zeigt_die_sorten(): void
    {
        $ticket = $this->ticket($this->kevin);

        Comment::create(['ticket_id' => $ticket->id, 'user_id' => $this->kundenzugang->id, 'body' => 'Erster vom Kunden', 'ist_intern' => false]);
        Comment::create(['ticket_id' => $ticket->id, 'user_id' => $this->kevin->id, 'body' => 'Zweite intern', 'ist_intern' => true, 'frage_an' => $this->nils->id]);

        Livewire::actingAs($this->nils)
            ->test(Ticketverlauf::class, ['ticket' => $ticket])
            ->assertSeeInOrder(['Erster vom Kunden', 'Zweite intern'])
            ->assertSee('Vom Kunden')
            ->assertSee('Interne Notiz')
            ->assertSee('Frage an dich');
    }

    public function test_offene_fragen_stehen_auf_der_bruecke_und_an_der_karte(): void
    {
        $ticket = $this->ticket($this->kevin);
        $this->actingAs($this->nils);
        $this->assertFalse(OffeneFragen::canView());

        Comment::create(['ticket_id' => $ticket->id, 'user_id' => $this->kevin->id, 'body' => 'Welche Farbe?', 'ist_intern' => true, 'frage_an' => $this->nils->id]);

        $this->actingAs($this->nils);
        $this->assertTrue(OffeneFragen::canView());

        Livewire::actingAs($this->nils)
            ->test(OffeneFragen::class)
            ->assertSee('Welche Farbe?');

        Livewire::actingAs($this->nils)
            ->test(Kanban::class)
            ->assertSee('an dich');

        // Kevin hat gefragt — für ihn ist es keine Frage an ihn.
        $this->actingAs($this->kevin);
        $this->assertFalse(OffeneFragen::canView());
    }
}
