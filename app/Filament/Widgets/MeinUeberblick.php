<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Support\Dauer;
use App\Support\Sichtbarkeit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Die vier Zahlen, mit denen man den Tag anfängt.
 *
 * Alles hier ist auf den angemeldeten Nutzer bezogen und läuft über
 * sichtbarFuer — ein Mitarbeiter sieht in den Zählern nichts aus fremden
 * Projekten. Ohne diesen Scope wäre das Dashboard das Leck, durch das die
 * Rollentrennung wieder verloren geht.
 */
class MeinUeberblick extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * Die Überschrift bleibt, obwohl die Betriebszahlen nicht mehr daneben
     * stehen. Sie beantwortet weiterhin die Frage, die man vor einer Zahl
     * hat: meint sie mich oder alles, was läuft. Die Antwort steht jetzt
     * zusätzlich schon im Seitentitel — doppelt ist hier richtig, denn eine
     * Kachelreihe wird auch quer gelesen.
     */
    protected ?string $heading = 'Meine Arbeit';

    /**
     * Volle Breite.
     *
     * Bis zur Trennung von "Mein Bereich" und "Betrieb" stand hier eine
     * Rechnung: halbe Breite, sobald TeamUeberblick daneben sichtbar war.
     * Die Zahlen des Betriebs stehen jetzt auf einer eigenen Seite, neben
     * diesen hier steht also nichts mehr — und eine halbe Reihe Kacheln mit
     * leerer Fläche daneben sieht aus, als fehle etwas.
     */
    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $nutzer = auth()->user();

        // Ohne Projektzuordnung sind alle vier Zahlen null, und zwar ohne
        // erkennbaren Grund. Statt vier Nullen anzuzeigen, die wie ein
        // kaputtes System aussehen, wird hier gesagt, was fehlt.
        if (Sichtbarkeit::ohneProjekte($nutzer)) {
            return [
                Stat::make('Keine Zuordnung', '—')
                    ->description('Ein Administrator muss dich unter Maschinenraum → Crew einem Kunden oder einzelnen Projekten zuordnen. Bis dahin siehst du keine Kunden, Projekte oder Tickets.')
                    ->descriptionIcon('heroicon-m-information-circle')
                    ->color('warning'),
            ];
        }

        $meine = Ticket::query()
            ->sichtbarFuer($nutzer)
            ->offen()
            ->where('assigned_to', $nutzer->getKey());

        $ueberfaellig = (clone $meine)
            ->whereDate('faellig_am', '<', today())
            ->count();

        $dieseWoche = (clone $meine)
            ->whereBetween('faellig_am', [today(), today()->endOfWeek()])
            ->count();

        // Wohin die Kacheln führen. Die Reiterschlüssel stehen in
        // ListTickets::getTabs(), die Filterwerte in TicketsTable; wie die
        // Adresse zusammengesetzt sein muss, damit die Liste danach genau
        // diese Menge zeigt, steht in TicketResource::listeUrl().
        $liste = TicketResource::listeUrl(...);

        $minutenDieseWoche = TimeEntry::query()
            ->where('user_id', $nutzer->getKey())
            ->where('gestartet_am', '>=', today()->startOfWeek())
            ->sum('minuten');

        return [
            Stat::make('Meine offenen Tickets', (string) $meine->count())
                ->description($ueberfaellig > 0 ? "{$ueberfaellig} davon überfällig" : 'nichts überfällig')
                ->descriptionIcon($ueberfaellig > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($ueberfaellig > 0 ? 'danger' : 'success')
                // Auf alle meine offenen, auch wenn welche überfällig sind:
                // die Zahl auf der Kachel ist die Gesamtzahl, und eine Kachel,
                // die auf weniger Zeilen führt, als sie anzeigt, ist genau die
                // Art von Ungenauigkeit, die man einmal bemerkt und der man
                // danach nicht mehr traut. Die Überfälligen stehen im Reiter
                // daneben, mit ihrer eigenen roten Zahl.
                ->url($liste('meine')),

            Stat::make('Fällig bis Sonntag', (string) $dieseWoche)
                ->description('aus meinen Tickets')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($dieseWoche > 0 ? 'warning' : 'gray')
                ->url($liste('meine', 'faellig-diese-woche')),

            // Ohne Adresse, und das bleibt so: erfasste Zeiten haben keine
            // eigene Liste, sie hängen an den Tickets. Eine Kachel, die
            // irgendwohin führt, weil die beiden daneben es auch tun, ist
            // schlechter als eine, die stehen bleibt.
            Stat::make('Meine Zeit diese Woche', Dauer::alsStunden((int) $minutenDieseWoche))
                ->description('seit Montag erfasst')
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),

            // Bewusst keine vierte Kachel mit den unzugewiesenen Tickets:
            // Zuteilen ist Sache des Administrators, und eine Zahl, auf die
            // man nicht handeln kann, ist auf einem Dashboard nur Beiwerk.
            // Für den Administrator steht sie nebenan unter "Im Betrieb".
        ];
    }
}
