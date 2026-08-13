<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Models\TimeEntry;
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

    protected function getStats(): array
    {
        $nutzer = auth()->user();

        // Ohne Projektzuordnung sind alle vier Zahlen null, und zwar ohne
        // erkennbaren Grund. Statt vier Nullen anzuzeigen, die wie ein
        // kaputtes System aussehen, wird hier gesagt, was fehlt.
        if (Sichtbarkeit::ohneProjekte($nutzer)) {
            return [
                Stat::make('Kein Projekt zugeordnet', '—')
                    ->description('Ein Administrator muss dich unter Verwaltung → Nutzer einem Projekt zuordnen. Bis dahin siehst du keine Kunden, Projekte oder Tickets.')
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

        $unzugewiesen = Ticket::query()
            ->sichtbarFuer($nutzer)
            ->offen()
            ->whereNull('assigned_to')
            ->count();

        return [
            Stat::make('Meine offenen Tickets', (string) $meine->count())
                ->description($ueberfaellig > 0 ? "{$ueberfaellig} davon überfällig" : 'nichts überfällig')
                ->descriptionIcon($ueberfaellig > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($ueberfaellig > 0 ? 'danger' : 'success'),

            Stat::make('Fällig bis Sonntag', (string) $dieseWoche)
                ->description('aus meinen Tickets')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($dieseWoche > 0 ? 'warning' : 'gray'),

            Stat::make('Meine Zeit diese Woche', $this->alsStunden((int) $minutenDieseWoche))
                ->description('seit Montag erfasst')
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),

            Stat::make('Unzugewiesen', (string) $unzugewiesen)
                ->description('offen, noch niemandem zugeteilt')
                ->descriptionIcon('heroicon-m-inbox')
                ->color($unzugewiesen > 0 ? 'warning' : 'gray'),
        ];
    }

    private function alsStunden(int $minuten): string
    {
        return intdiv($minuten, 60).':'.str_pad((string) ($minuten % 60), 2, '0', STR_PAD_LEFT).' h';
    }
}
