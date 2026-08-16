<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Support\Dauer;
use App\Support\Raster;
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
     * Überschrift, weil rechts daneben vier weitere Kacheln stehen. Ohne sie
     * sähe man acht gleich aussehende Zahlen und wüsste bei keiner, ob sie
     * die eigene Arbeit meint oder alles, was im Projekt läuft.
     */
    protected ?string $heading = 'Meine Arbeit';

    /**
     * Halbe Breite, sobald die Projektzahlen daneben stehen. Zwei volle
     * Reihen Kacheln übereinander hätten das eigentliche Dashboard — Tickets
     * und Geschehen — unter den Bildschirmrand geschoben.
     */
    public function getColumnSpan(): int|string|array
    {
        return TeamUeberblick::canView() ? Raster::HALB : 'full';
    }

    protected function getColumns(): int|array|null
    {
        // Halbe Breite trägt zwei Kacheln nebeneinander, volle drei — und
        // unterhalb von xl steht die Karte wieder über die ganze Breite, dann
        // passen dort auch wieder alle drei nebeneinander.
        return TeamUeberblick::canView() ? ['default' => 3, 'xl' => 2] : 3;
    }

    protected function getStats(): array
    {
        $nutzer = auth()->user();

        // Ohne Projektzuordnung sind alle vier Zahlen null, und zwar ohne
        // erkennbaren Grund. Statt vier Nullen anzuzeigen, die wie ein
        // kaputtes System aussehen, wird hier gesagt, was fehlt.
        if (Sichtbarkeit::ohneProjekte($nutzer)) {
            return [
                Stat::make('Keine Zuordnung', '—')
                    ->description('Ein Administrator muss dich unter Verwaltung → Nutzer einem Kunden oder einzelnen Projekten zuordnen. Bis dahin siehst du keine Kunden, Projekte oder Tickets.')
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

        $minutenDieseWoche = TimeEntry::query()
            ->where('user_id', $nutzer->getKey())
            ->where('gestartet_am', '>=', today()->startOfWeek())
            ->sum('minuten');

        return [
            Stat::make('Meine offenen Tickets', (string) $meine->count())
                ->description($ueberfaellig > 0 ? "{$ueberfaellig} davon überfällig" : 'nichts überfällig')
                ->descriptionIcon($ueberfaellig > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($ueberfaellig > 0 ? 'danger' : 'success'),

            Stat::make('Fällig bis Sonntag', (string) $dieseWoche)
                ->description('aus meinen Tickets')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($dieseWoche > 0 ? 'warning' : 'gray'),

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
