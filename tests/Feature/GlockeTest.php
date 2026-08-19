<?php

namespace Tests\Feature;

use App\Enums\MailEreignis;
use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Filament\Kunde\Pages\Nachrichten as KundenNachrichten;
use App\Filament\Kunde\Resources\Anliegen\Pages\ViewAnliegen;
use App\Filament\Pages\Nachrichten as InterneNachrichten;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Customer;
use App\Models\Nachricht;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use App\Support\Unterhaltungen;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die Zahl an der Glocke.
 *
 * Der Anlass: sie zählte nur dann herunter, wenn man eine Meldung in der
 * Glocke selbst anklickte. Wer die Antwort längst im Ticket gelesen hatte,
 * trug sie trotzdem weiter vor sich her — und eine Zahl, die immer da ist,
 * heißt nach der dritten Woche nichts mehr.
 *
 * Geprüft wird deshalb beides: dass Gesehenes verstummt, und dass dabei nur
 * genau das verstummt, was man auch wirklich gesehen hat.
 */
class GlockeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
    }

    private function kunde(?Customer $customer = null): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => ($customer ?? Customer::factory()->create())->getKey(),
        ]);
    }

    /** Ein Ticket, das ein Kunde selbst gemeldet hat — das löst die Meldung nach innen aus. */
    private function anliegenVomKunden(Customer $customer): Ticket
    {
        return Ticket::factory()
            ->for(Project::factory()->for($customer, 'customer'), 'project')
            ->create([
                'customer_id' => $customer->getKey(),
                'ticket_status_id' => TicketStatus::factory()->create()->getKey(),
                'quelle' => Quelle::Kunde,
            ]);
    }

    // --- Nachrichten --------------------------------------------------

    public function test_ein_geoeffneter_verlauf_setzt_die_glocke_zurueck(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kunde($kunde);
        $admin = $this->admin();

        $verlauf = Unterhaltungen::fuerKunden($kunde);

        Nachricht::create([
            'unterhaltung_id' => $verlauf->getKey(),
            'user_id' => $zugang->getKey(),
            'text' => 'Kurze Frage',
        ]);

        $this->assertSame(1, $admin->unreadNotifications()->count());

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(InterneNachrichten::class)
            ->call('oeffnen', $verlauf->getKey());

        $this->assertSame(0, $admin->unreadNotifications()->count(), 'Die Glocke zählt noch.');
    }

    public function test_die_gelesene_meldung_bleibt_in_der_glocke_stehen(): void
    {
        // Sie soll verstummen, nicht verschwinden: die Glocke ist auch ein
        // kleines Gedächtnis dafür, wann etwas hereinkam.
        $kunde = Customer::factory()->create();
        $zugang = $this->kunde($kunde);
        $admin = $this->admin();

        $verlauf = Unterhaltungen::fuerKunden($kunde);

        Nachricht::create([
            'unterhaltung_id' => $verlauf->getKey(),
            'user_id' => $zugang->getKey(),
            'text' => 'Kurze Frage',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(InterneNachrichten::class)->call('oeffnen', $verlauf->getKey());

        $this->assertSame(1, $admin->notifications()->count(), 'Die Meldung ist verschwunden.');
        $this->assertNotNull($admin->notifications()->first()->read_at);
    }

    public function test_auch_der_kunde_bekommt_seine_glocke_zurueckgesetzt(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kunde($kunde);
        $admin = $this->admin();

        $verlauf = Unterhaltungen::fuerKunden($kunde);

        Nachricht::create([
            'unterhaltung_id' => $verlauf->getKey(),
            'user_id' => $admin->getKey(),
            'text' => 'Donnerstag um zehn?',
        ]);

        $this->assertSame(1, $zugang->unreadNotifications()->count());

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(KundenNachrichten::class)->assertOk();

        $this->assertSame(0, $zugang->unreadNotifications()->count());
    }

    public function test_ein_fremder_verlauf_laesst_die_glocke_in_ruhe(): void
    {
        // Der eigentliche Fehler, den man hier bauen kann: beim Öffnen
        // pauschal alles als gelesen markieren. Dann verschwände mit dem
        // einen Verlauf auch alles andere, was noch offen ist.
        $einer = Customer::factory()->create();
        $anderer = Customer::factory()->create();

        $admin = $this->admin();

        $verlaufA = Unterhaltungen::fuerKunden($einer);
        $verlaufB = Unterhaltungen::fuerKunden($anderer);

        foreach ([[$verlaufA, $this->kunde($einer)], [$verlaufB, $this->kunde($anderer)]] as [$verlauf, $zugang]) {
            Nachricht::create([
                'unterhaltung_id' => $verlauf->getKey(),
                'user_id' => $zugang->getKey(),
                'text' => 'Frage',
            ]);
        }

        $this->assertSame(2, $admin->unreadNotifications()->count());

        // Verlauf A nach oben zwingen. Die Seite öffnet beim Aufbau den
        // obersten Faden und markiert ihn dabei als gelesen — ohne diese
        // Zeile entschiede die Sortierung, ob das A oder B trifft, und der
        // Test prüfte mal das eine und mal das andere.
        $verlaufA->forceFill(['letzte_nachricht_am' => now()->addMinute()])->save();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(InterneNachrichten::class)->call('oeffnen', $verlaufA->getKey());

        $this->assertSame(1, $admin->unreadNotifications()->count(), 'Es wurde zu viel als gelesen markiert.');
    }

    // --- Tickets ------------------------------------------------------

    public function test_ein_geoeffnetes_ticket_setzt_die_glocke_zurueck(): void
    {
        $admin = $this->admin();
        $ticket = $this->anliegenVomKunden(Customer::factory()->create());

        $this->assertSame(1, $admin->unreadNotifications()->count());

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])->assertOk();

        $this->assertSame(0, $admin->unreadNotifications()->count());
        $this->assertSame(1, $admin->notifications()->count(), 'Die Meldung ist verschwunden.');
    }

    public function test_ein_anderes_ticket_laesst_die_meldung_stehen(): void
    {
        $admin = $this->admin();

        $eines = $this->anliegenVomKunden(Customer::factory()->create());
        $anderes = $this->anliegenVomKunden(Customer::factory()->create());

        $this->assertSame(2, $admin->unreadNotifications()->count());

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewTicket::class, ['record' => $eines->getKey()])->assertOk();

        $this->assertSame(1, $admin->unreadNotifications()->count());
        $this->assertSame($anderes->getKey(), (int) str($admin->unreadNotifications()->first()->data['herkunft'])->after('ticket:')->toString());
    }

    public function test_der_kunde_liest_sein_anliegen_und_die_glocke_verstummt(): void
    {
        $kunde = Customer::factory()->create();
        $zugang = $this->kunde($kunde);
        $admin = $this->admin();

        $ticket = $this->anliegenVomKunden($kunde);
        $ticket->project->update(['kunden_sichtbar' => true]);

        // Eine Antwort von uns — sie ist es, die beim Kunden ankommt.
        $ticket->comments()->create([
            'user_id' => $admin->getKey(),
            'body' => 'Wir schauen uns das an.',
            'ist_intern' => false,
        ]);

        $this->assertSame(1, $zugang->unreadNotifications()->count());

        $this->actingAs($zugang, 'kunde');
        Filament::setCurrentPanel('kunde');

        Livewire::test(ViewAnliegen::class, ['record' => $ticket->getKey()])->assertOk();

        $this->assertSame(0, $zugang->unreadNotifications()->count());
    }

    // --- Kundenakte ---------------------------------------------------

    public function test_die_geoeffnete_kundenakte_setzt_ihre_meldung_zurueck(): void
    {
        $kunde = Customer::factory()->create();
        $admin = $this->admin();

        Benachrichtigung::anZustaendige(
            $kunde->getKey(),
            Notification::make()->title('Stammdaten geändert'),
            MailEreignis::Stammdaten,
        );

        $this->assertSame(1, $admin->unreadNotifications()->count());

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(EditCustomer::class, ['record' => $kunde->getKey()])->assertOk();

        $this->assertSame(0, $admin->unreadNotifications()->count());
    }

    // --- Das Fundament ------------------------------------------------

    public function test_jede_meldung_traegt_ihre_herkunft(): void
    {
        // Ohne die Herkunft in den Daten kann nichts davon funktionieren, und
        // sie fehlte still: eine Meldung ohne sie sieht genauso aus wie eine
        // mit, sie lässt sich nur nie wieder zuordnen.
        $admin = $this->admin();
        $ticket = $this->anliegenVomKunden(Customer::factory()->create());

        $daten = $admin->notifications()->first()->data;

        $this->assertSame(Herkunft::ticket($ticket), $daten['herkunft'] ?? null);

        // Und Filament kommt mit dem zusätzlichen Schlüssel weiterhin klar —
        // die Glocke liest dieselbe Zeile zurück.
        $this->assertSame('filament', $daten['format'] ?? null);
    }
}
