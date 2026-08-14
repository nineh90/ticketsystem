<?php

namespace App\Filament\Kunde\Widgets;

use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Die drei Zahlen, mit denen der Kundenbereich aufmacht.
 *
 * Alle laufen über sichtbarFuer — auch hier, wo ohnehin nur die eigenen
 * Projekte gemeint sind. Genau an solchen Stellen entstehen Lecks: ein Zähler
 * ist schnell mit einer direkten Abfrage gebaut, weil er "ja nur eine Zahl"
 * ist, und zählt dann still über alle Kunden.
 */
class StandDerDinge extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $nutzer = auth()->user();

        $basis = fn () => Ticket::query()->sichtbarFuer($nutzer);

        $amZug = $basis()->wartetAufKunde()->count();
        $offen = $basis()->offen()->count();
        $erledigt = $basis()->whereHas('status', fn ($q) => $q->where('ist_abschluss', true))->count();

        return [
            // Zuerst und immer, auch wenn sie null ist: die Null ist hier die
            // eigentliche Information — "von mir wird gerade nichts erwartet".
            Stat::make('Sie sind am Zug', (string) $amZug)
                ->description($amZug > 0
                    ? 'Wir warten auf eine Rückmeldung von Ihnen'
                    : 'Nichts liegt bei Ihnen')
                ->descriptionIcon($amZug > 0 ? 'heroicon-m-hand-raised' : 'heroicon-m-check-circle')
                ->color($amZug > 0 ? 'warning' : 'success')
                ->url($amZug > 0 ? AnliegenResource::getUrl('index', ['activeTab' => 'am-zug']) : null),

            Stat::make('In Bearbeitung', (string) $offen)
                ->description('Anliegen, an denen wir arbeiten')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->url(AnliegenResource::getUrl('index', ['activeTab' => 'offen'])),

            Stat::make('Erledigt', (string) $erledigt)
                ->description('abgeschlossen')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('gray')
                ->url(AnliegenResource::getUrl('index', ['activeTab' => 'erledigt'])),
        ];
    }
}
