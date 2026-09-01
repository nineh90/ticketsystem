<?php

namespace Tests\Feature;

use App\Enums\Quelle;
use App\Enums\Rolle;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Was ein Kunde in seiner Anliegenliste sieht — und was nicht.
 *
 * Neu am 01.09.2026. Vorher sah er alle Tickets seiner freigegebenen
 * Projekte; bei Sarah Schweikert waren das 114 Zeilen, von denen 4 sie
 * betrafen. Jetzt gilt: was wir von ihm brauchen (Stand "Warten auf Kunde"),
 * und was er selbst gemeldet hat (quelle = kunde).
 *
 * Der wichtigste Test ist der letzte. Die Bedingung besteht aus einer
 * UND-Verknüpfung (richtiges Projekt) und einer ODER-Verknüpfung (am Zug
 * oder selbst gemeldet). Steht die zweite ohne Klammer, hängt das ODER am
 * Ende der ganzen Abfrage und hebelt die Projektprüfung aus — dann sähe
 * jeder Kunde jedes kundengemeldete Ticket. Das ist ein Leck, das keine
 * Oberfläche zeigt und das man nur bemerkt, wenn man ausdrücklich danach
 * fragt.
 */
class KundensichtAnliegenTest extends TestCase
{
    use RefreshDatabase;

    private function kundeMitProjekt(): array
    {
        $kunde = Customer::factory()->create();

        $nutzer = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $kunde->getKey(),
        ]);

        $projekt = Project::factory()->create([
            'customer_id' => $kunde->getKey(),
            'kunden_sichtbar' => true,
        ]);

        return [$nutzer, $projekt, $kunde];
    }

    private function stand(array $eigenschaften = []): TicketStatus
    {
        return TicketStatus::factory()->create($eigenschaften);
    }

    public function test_was_auf_den_kunden_wartet_ist_sichtbar(): void
    {
        [$nutzer, $projekt] = $this->kundeMitProjekt();

        $ticket = Ticket::factory()->create([
            'project_id' => $projekt->getKey(),
            'customer_id' => $projekt->customer_id,
            'quelle' => Quelle::Manuell,
            'ticket_status_id' => $this->stand(['wartet_auf_kunde' => true])->getKey(),
        ]);

        $this->assertTrue(
            Ticket::query()->sichtbarFuer($nutzer)->whereKey($ticket->getKey())->exists(),
        );
    }

    public function test_was_der_kunde_selbst_gemeldet_hat_ist_sichtbar(): void
    {
        [$nutzer, $projekt] = $this->kundeMitProjekt();

        $ticket = Ticket::factory()->create([
            'project_id' => $projekt->getKey(),
            'customer_id' => $projekt->customer_id,
            'quelle' => Quelle::Kunde,
            'created_by' => $nutzer->getKey(),
            'ticket_status_id' => $this->stand(['wartet_auf_kunde' => false])->getKey(),
        ]);

        $this->assertTrue(
            Ticket::query()->sichtbarFuer($nutzer)->whereKey($ticket->getKey())->exists(),
        );
    }

    /**
     * Der Kern der Änderung: unsere eigene Arbeit bleibt bei uns.
     */
    public function test_unsere_eigene_arbeit_sieht_der_kunde_nicht(): void
    {
        [$nutzer, $projekt] = $this->kundeMitProjekt();

        $ticket = Ticket::factory()->create([
            'project_id' => $projekt->getKey(),
            'customer_id' => $projekt->customer_id,
            'quelle' => Quelle::Manuell,
            'titel' => 'Hero überarbeiten',
            'ticket_status_id' => $this->stand(['wartet_auf_kunde' => false])->getKey(),
        ]);

        $this->assertFalse(
            Ticket::query()->sichtbarFuer($nutzer)->whereKey($ticket->getKey())->exists(),
            'Ein von uns angelegtes Ticket, bei dem nichts vom Kunden gebraucht wird, gehört nicht in seine Liste.',
        );
    }

    /**
     * Ein verborgenes Projekt bleibt verborgen — auch wenn wir dort etwas
     * vom Kunden brauchen.
     */
    public function test_aus_einem_verborgenen_projekt_kommt_nichts_durch(): void
    {
        [$nutzer, , $kunde] = $this->kundeMitProjekt();

        $verborgen = Project::factory()->create([
            'customer_id' => $kunde->getKey(),
            'kunden_sichtbar' => false,
        ]);

        $ticket = Ticket::factory()->create([
            'project_id' => $verborgen->getKey(),
            'customer_id' => $kunde->getKey(),
            'quelle' => Quelle::Manuell,
            'ticket_status_id' => $this->stand(['wartet_auf_kunde' => true])->getKey(),
        ]);

        $this->assertFalse(
            Ticket::query()->sichtbarFuer($nutzer)->whereKey($ticket->getKey())->exists(),
        );
    }

    /**
     * Die Gegenprobe zur Klammer: fremde Kunden bleiben fremd.
     *
     * Ohne die Klammer um "am Zug ODER selbst gemeldet" hinge das ODER am
     * Ende der gesamten Bedingung, und dieses Ticket käme durch — obwohl es
     * einem völlig anderen Kunden gehört.
     */
    public function test_ein_fremder_kunde_sieht_nichts_davon(): void
    {
        [$nutzer] = $this->kundeMitProjekt();

        $fremderKunde = Customer::factory()->create();

        $fremderNutzer = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => $fremderKunde->getKey(),
        ]);

        $fremdesProjekt = Project::factory()->create([
            'customer_id' => $fremderKunde->getKey(),
            'kunden_sichtbar' => true,
        ]);

        $fremdesTicket = Ticket::factory()->create([
            'project_id' => $fremdesProjekt->getKey(),
            'customer_id' => $fremderKunde->getKey(),
            'quelle' => Quelle::Kunde,
            'created_by' => $fremderNutzer->getKey(),
            'ticket_status_id' => $this->stand(['wartet_auf_kunde' => true])->getKey(),
        ]);

        $this->assertFalse(
            Ticket::query()->sichtbarFuer($nutzer)->whereKey($fremdesTicket->getKey())->exists(),
            'Das ist das Leck, gegen das die Klammer in scopeSichtbarFuer steht.',
        );

        // Und die Gegenrichtung, damit der Test nicht bloß deshalb grün ist,
        // weil gar nichts sichtbar wäre.
        $this->assertTrue(
            Ticket::query()->sichtbarFuer($fremderNutzer)->whereKey($fremdesTicket->getKey())->exists(),
        );
    }

    /**
     * Für uns ändert sich nichts.
     *
     * Die Regel steht in einem Scope, den auch das interne Panel benutzt —
     * ein Fehler darin nähme uns die halbe Ticketliste weg.
     */
    public function test_intern_bleibt_alles_sichtbar(): void
    {
        [, $projekt] = $this->kundeMitProjekt();

        $ticket = Ticket::factory()->create([
            'project_id' => $projekt->getKey(),
            'customer_id' => $projekt->customer_id,
            'quelle' => Quelle::Manuell,
            'ticket_status_id' => $this->stand(['wartet_auf_kunde' => false])->getKey(),
        ]);

        $admin = User::factory()->create(['rolle' => Rolle::Admin, 'panel_zugang' => true]);

        $this->assertTrue(
            Ticket::query()->sichtbarFuer($admin)->whereKey($ticket->getKey())->exists(),
        );
    }
}
