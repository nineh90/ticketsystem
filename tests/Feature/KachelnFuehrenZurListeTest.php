<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Kunde\Resources\Anliegen\Pages\ListAnliegen;
use App\Filament\Kunde\Widgets\StandDerDinge;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Widgets\MeinUeberblick;
use App\Filament\Widgets\TeamUeberblick;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Der Vertrag zwischen Kachel und Liste.
 *
 * Seit die Zahlen auf dem Dashboard anklickbar sind, sind sie ein Versprechen:
 * wer auf eine 4 klickt, will vier Zeilen sehen. Das ist keine Kleinigkeit —
 * die Bedingung steht auf der einen Seite in einem Zähler, auf der anderen in
 * einem Reiter plus einem Filter, und beide lassen sich unabhängig ändern.
 * Genau deshalb prüft dieser Test nicht einzelne Adressen, sondern jede Kachel,
 * die eine hat: er nimmt ihre URL, setzt die Liste mit genau diesen
 * Abfrageparametern auf und zählt nach.
 *
 * Der Umweg über withQueryParams ist Absicht. Die Kachel könnte den Reiter
 * korrekt benennen und die Adresse trotzdem verloren gehen, weil Filament den
 * Parameter anders liest, als das Widget ihn schreibt — dann steht die Liste
 * still auf "Offen" und niemand merkt es, weil die Seite ja aufgeht.
 */
class KachelnFuehrenZurListeTest extends TestCase
{
    use RefreshDatabase;

    public function test_jede_kachel_in_meine_arbeit_zeigt_so_viele_tickets_wie_sie_verspricht(): void
    {
        $nutzer = $this->admin();
        $this->beispieldaten($nutzer);

        $this->pruefeKacheln(MeinUeberblick::class, $nutzer);
    }

    public function test_jede_kachel_im_betrieb_zeigt_so_viele_tickets_wie_sie_verspricht(): void
    {
        $nutzer = $this->admin();
        $this->beispieldaten($nutzer);

        $this->pruefeKacheln(TeamUeberblick::class, $nutzer);
    }

    public function test_jede_kachel_im_kundenbereich_zeigt_so_viele_anliegen_wie_sie_verspricht(): void
    {
        // Der Kundenbereich hatte die Kacheln zuerst — und mit ihnen den
        // Parameternamen aus der Zeit davor. Er stand still falsch da:
        // "activeTab" statt "tab", also ging die Liste immer auf ihrem
        // Standardreiter auf, und weil der bei wartenden Anliegen ohnehin
        // "Sie sind am Zug" heißt, sah es meistens richtig aus.
        $kunde = $this->kunde();
        $this->anliegen($kunde);

        // Beides nötig: der eigene Guard, sonst steht die Livewire-Komponente
        // ohne angemeldeten Kunden da — und das Panel, sonst baut getUrl() die
        // Adresse im Adminpanel zusammen, wo es die Anliegen gar nicht gibt.
        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        $verlinkte = collect($this->kacheln(StandDerDinge::class, $kunde))
            ->filter(fn (Stat $kachel) => $kachel->getUrl() !== null);

        $this->assertCount(3, $verlinkte);

        foreach ($verlinkte as $kachel) {
            parse_str((string) parse_url($kachel->getUrl(), PHP_URL_QUERY), $parameter);

            $anzahl = Livewire::withQueryParams($parameter)
                ->test(ListAnliegen::class)
                ->instance()
                ->getFilteredTableQuery()
                ->count();

            $this->assertSame(
                (int) $kachel->getValue(),
                $anzahl,
                'Die Kachel "'.$kachel->getLabel().'" führt auf eine Liste mit anderer Länge.',
            );
        }
    }

    public function test_die_zeitkacheln_bleiben_bewusst_ohne_adresse(): void
    {
        // Sonst führt der nächste Handgriff sie "der Vollständigkeit halber"
        // irgendwohin — und irgendwohin heißt hier: auf eine Ticketliste, die
        // mit gebuchten Stunden nichts zu tun hat.
        $nutzer = $this->admin();
        $this->beispieldaten($nutzer);

        $ohneAdresse = collect($this->kacheln(MeinUeberblick::class, $nutzer))
            ->merge($this->kacheln(TeamUeberblick::class, $nutzer))
            ->filter(fn (Stat $kachel) => $kachel->getUrl() === null)
            ->map(fn (Stat $kachel) => $kachel->getLabel())
            ->values()
            ->all();

        $this->assertSame(['Meine Zeit diese Woche', 'Zeit heute'], $ohneAdresse);
    }

    public function test_kachel_ohne_zuordnung_verlinkt_nicht_ins_leere(): void
    {
        // Wer keinem Projekt zugeordnet ist, sieht statt vier Zahlen einen
        // Hinweis. Eine Adresse an dieser Karte führte auf eine leere Liste
        // und damit weg von der einzigen Erklärung, die es gibt.
        $ohneProjekte = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $kacheln = $this->kacheln(MeinUeberblick::class, $ohneProjekte);

        $this->assertCount(1, $kacheln);
        $this->assertNull($kacheln[0]->getUrl());
    }

    /**
     * Nimmt jede verlinkte Kachel beim Wort: Zahl auf der Kachel gegen Zeilen
     * hinter ihrer Adresse.
     */
    private function pruefeKacheln(string $widget, User $nutzer): void
    {
        $verlinkte = collect($this->kacheln($widget, $nutzer))
            ->filter(fn (Stat $kachel) => $kachel->getUrl() !== null);

        $this->assertGreaterThan(0, $verlinkte->count(), 'Keine einzige Kachel führt irgendwohin.');

        foreach ($verlinkte as $kachel) {
            $this->assertSame(
                (int) $kachel->getValue(),
                $this->tickets($kachel->getUrl(), $nutzer),
                'Die Kachel "'.$kachel->getLabel().'" führt auf eine Liste mit anderer Länge.',
            );
        }
    }

    /** Wie viele Tickets die Ticketliste unter dieser Adresse zeigt. */
    private function tickets(string $adresse, User $nutzer): int
    {
        parse_str((string) parse_url($adresse, PHP_URL_QUERY), $parameter);

        return Livewire::withQueryParams($parameter)
            ->actingAs($nutzer)
            ->test(ListTickets::class)
            ->instance()
            ->getFilteredTableQuery()
            ->count();
    }

    /**
     * getStats() ist geschützt — das ist richtig so, es ist kein Teil der
     * Widget-Schnittstelle. Für diesen Test ist es aber genau das Richtige:
     * er soll die Kacheln selbst prüfen und nicht das gerenderte HTML.
     *
     * @return array<int, Stat>
     */
    private function kacheln(string $widget, User $nutzer): array
    {
        $this->actingAs($nutzer, $nutzer->rolle === Rolle::Kunde ? 'kunde' : 'web');

        $instanz = Livewire::test($widget)->instance();

        $getStats = new ReflectionMethod($instanz, 'getStats');
        $getStats->setAccessible(true);

        return $getStats->invoke($instanz);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
    }

    private function kunde(): User
    {
        return User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => Customer::factory()->create()->getKey(),
        ]);
    }

    /** Je ein Anliegen in jedem der drei Zustände, auf die die Kacheln zeigen. */
    private function anliegen(User $kunde): void
    {
        $projekt = Project::factory()->create([
            'customer_id' => $kunde->customer_id,
            'kunden_sichtbar' => true,
        ]);

        $wartet = TicketStatus::factory()->create([
            'ist_abschluss' => false,
            'wartet_auf_kunde' => true,
        ]);
        $laeuft = TicketStatus::factory()->create([
            'ist_abschluss' => false,
            'wartet_auf_kunde' => false,
        ]);
        $fertig = TicketStatus::factory()->abschluss()->create();

        Ticket::factory()->for($projekt, 'project')->create(['ticket_status_id' => $wartet->id]);
        Ticket::factory()->count(2)->for($projekt, 'project')->create(['ticket_status_id' => $laeuft->id]);
        Ticket::factory()->count(3)->for($projekt, 'project')->create(['ticket_status_id' => $fertig->id]);
    }

    /**
     * Ein Bestand, in dem jede Kachel eine Zahl größer null trägt.
     *
     * Ohne das prüfte der Test lauter Nullen gegen lauter Nullen und ginge
     * auch dann durch, wenn Reiter und Filter überhaupt nicht greifen.
     */
    private function beispieldaten(User $nutzer): void
    {
        $offen = TicketStatus::factory()->create(['ist_abschluss' => false]);
        $fertig = TicketStatus::factory()->abschluss()->create();

        $projekt = Project::factory()->create();
        $projekt->mitarbeiter()->attach($nutzer);

        // Überfällig und mir zugewiesen.
        Ticket::factory()->count(2)->for($projekt, 'project')->create([
            'ticket_status_id' => $offen->id,
            'assigned_to' => $nutzer->id,
            'faellig_am' => today()->subDays(4),
        ]);

        // Diese Woche fällig und mir zugewiesen.
        Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $offen->id,
            'assigned_to' => $nutzer->id,
            'faellig_am' => today(),
        ]);

        // Niemandem zugeteilt.
        Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $offen->id,
            'assigned_to' => null,
        ]);

        // Heute schon wieder erledigt — zählt bei "Heute eingegangen" mit,
        // und genau deshalb führt diese Kachel auf den Reiter "Alle".
        Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $fertig->id,
            'assigned_to' => $nutzer->id,
        ]);

        // Liegt seit einer Woche unberührt.
        $ruhend = Ticket::factory()->for($projekt, 'project')->create([
            'ticket_status_id' => $offen->id,
            'assigned_to' => $nutzer->id,
        ]);
        $ruhend->forceFill(['created_at' => now()->subDays(9), 'updated_at' => now()->subDays(9)])->saveQuietly();
    }
}
