<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Dauer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Der Blick aufs Ganze — nur für Administratoren.
 *
 * MeinUeberblick beantwortet "was muss ich heute tun". Diese Zahlen
 * beantworten die andere Frage: was läuft gerade, unabhängig davon, wem es
 * zugewiesen ist.
 *
 * Bewusst nicht für Mitarbeiter: acht Kacheln über der eigentlichen Arbeit
 * sind keine Übersicht mehr, sondern eine Wand aus Zahlen. Wer an einem
 * Ticket sitzt, braucht oben seine vier — was er offen hat, was diese Woche
 * fällig ist, was frei herumliegt und wie viel Zeit er gebucht hat. Wie
 * ausgelastet das Projekt insgesamt ist, steht im Diagramm unten.
 *
 * Die Abfragen laufen trotzdem über sichtbarFuer statt über ungefilterte
 * Gesamtsummen — sonst hinge die Rollentrennung dieses Widgets allein an
 * canView(), und die wäre beim nächsten "zeig das doch auch dem Team"
 * lautlos weg.
 */
class TeamUeberblick extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    /**
     * Volle Breite und vier Kacheln nebeneinander.
     *
     * Vorher halb, weil MeinUeberblick daneben stand. Seit der Trennung steht
     * es das nicht mehr — die eigenen Zahlen sind auf "Mein Bereich"
     * gewandert, hier oben ist die Reihe frei.
     */
    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    protected ?string $heading = 'Im Betrieb';

    public static function canView(): bool
    {
        return auth()->user()?->istAdmin() ?? false;
    }

    protected function getStats(): array
    {
        /** @var User $nutzer */
        $nutzer = auth()->user();

        $sichtbar = fn (): Builder => Ticket::query()->sichtbarFuer($nutzer);

        $offen = $sichtbar()->offen()->count();

        $ueberfaellig = $sichtbar()->ueberfaellig()->count();

        // Was offen ist und noch niemandem gehört. Steht als Beschreibung an
        // der Gesamtzahl statt in einer eigenen Kachel: es ist kein zweiter
        // Sachverhalt, sondern ein Teil derselben Menge.
        $unzugewiesen = $sichtbar()
            ->offen()
            ->whereNull('assigned_to')
            ->count();

        $neuHeute = $sichtbar()
            ->whereDate('created_at', today())
            ->count();

        $erledigtHeute = $sichtbar()
            ->whereDate('erledigt_at', today())
            ->count();

        $ruhend = $sichtbar()->ruhend()->count();

        // Bewusst die Zeit aller Beteiligten, nicht nur die eigene: die eigene
        // steht schon nebenan in MeinUeberblick. Hier geht es darum, was
        // heute insgesamt in diese Projekte geflossen ist.
        $minutenHeute = TimeEntry::query()
            ->whereIn('ticket_id', $sichtbar()->select('tickets.id'))
            ->whereDate('gestartet_am', today())
            ->sum('minuten');

        // Wohin die Kacheln führen. Reiterschlüssel aus ListTickets,
        // Filterwerte aus TicketsTable; die Bedingungen dahinter sind
        // dieselben Scopes, die oben gezählt haben. Zu den Parameternamen
        // steht das Nötige in MeinUeberblick.
        $liste = fn (string $reiter, ?string $zeitfenster = null) => TicketResource::getUrl('index', array_filter([
            'tab' => $reiter,
            'filters' => $zeitfenster ? ['zeitfenster' => ['value' => $zeitfenster]] : null,
        ]));

        return [
            // Die Beschreibung nennt zwei Teilmengen, die Kachel kann nur
            // eine Adresse haben — sie führt auf die Gesamtmenge. "Überfällig"
            // und "Unzugewiesen" stehen dort als eigene Reiter mit ihrer Zahl
            // direkt über der Liste, also einen Klick weiter und sichtbar.
            Stat::make('Offen gesamt', (string) $offen)
                ->description(
                    ($ueberfaellig > 0 ? "{$ueberfaellig} überfällig" : 'nichts überfällig')
                    .', '.$unzugewiesen.' nicht zugeteilt',
                )
                ->descriptionIcon($ueberfaellig > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($ueberfaellig > 0 ? 'danger' : 'success')
                ->url($liste('offen')),

            // Reiter "Alle", nicht "Offen": gezählt wird alles, was heute
            // hereinkam, auch das, was schon wieder erledigt ist.
            Stat::make('Heute eingegangen', (string) $neuHeute)
                ->description($erledigtHeute.' heute erledigt')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color($neuHeute > 0 ? 'primary' : 'gray')
                ->url($liste('alle', 'heute-eingegangen')),

            Stat::make('Liegt seit '.Ticket::RUHEND_AB_TAGEN.' Tagen', (string) $ruhend)
                ->description('offen, ohne Änderung und ohne Kommentar')
                ->descriptionIcon('heroicon-m-moon')
                ->color($ruhend > 0 ? 'warning' : 'gray')
                ->url($liste('offen', 'ruhend')),

            // Wie nebenan in "Meine Zeit diese Woche" ohne Adresse: erfasste
            // Zeiten haben keine eigene Liste.
            Stat::make('Zeit heute', Dauer::alsStunden((int) $minutenHeute))
                ->description('von allen Beteiligten erfasst')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
