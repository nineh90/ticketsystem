<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\OeffnetDasLogbuch;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Support\Dauer;
use App\Support\Logbuch;
use App\Support\Sichtbarkeit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

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
    use OeffnetDasLogbuch;

    protected static ?int $sort = 1;

    /**
     * Eigener View statt des Filament-eigenen: er ist derselbe, nur mit dem
     * Fenster hinter der Logbuch-Kachel darin. Warum das in dieser Komponente
     * stehen muss, steht in der Datei.
     */
    protected string $view = 'filament.widgets.ueberblick-mit-logbuch';

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

        // Summe und Auflistung im Fenster kommen aus derselben Abfrage,
        // siehe Support\Logbuch.
        $minutenDieseWoche = Logbuch::eigeneWocheAbfrage($nutzer)->sum('minuten');

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

            // Weiterhin ohne Adresse, aber nicht mehr stumm: erfasste Zeiten
            // haben keine eigene Liste, sie hängen an den Tickets. Statt die
            // Kachel irgendwohin führen zu lassen, wo man die Woche wieder
            // zusammensuchen müsste, klappt sie auf — Tag für Tag, was man
            // gebucht hat. Dieselbe Mechanik wie bei "Zeit heute" auf der
            // Brücke, siehe Concerns\OeffnetDasLogbuch.
            Stat::make('Mein Logbuch diese Woche', Dauer::alsStunden((int) $minutenDieseWoche))
                ->description('seit Montag erfasst — anklicken')
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary')
                ->extraAttributes($this->logbuchKachel()),

            // Bewusst keine vierte Kachel mit den unzugewiesenen Tickets:
            // Zuteilen ist Sache des Administrators, und eine Zahl, auf die
            // man nicht handeln kann, ist auf einem Dashboard nur Beiwerk.
            // Für den Administrator steht sie nebenan unter "Im Betrieb".
        ];
    }

    public function logbuchId(): string
    {
        return 'logbuch-eigenes';
    }

    public function logbuchTitel(): string
    {
        return 'Mein Logbuch';
    }

    public function logbuchBeschreibung(): string
    {
        return 'Was du seit Montag erfasst hast — Tag für Tag.';
    }

    /** Hier steht in jeder Zeile derselbe Name. Der bleibt weg. */
    public function logbuchMitNamen(): bool
    {
        return false;
    }

    /** @return Collection<int, TimeEntry> */
    protected function logbuchZeiten(): Collection
    {
        $nutzer = auth()->user();

        return $nutzer ? Logbuch::eigeneWoche($nutzer) : collect();
    }
}
