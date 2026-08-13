<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\ChartWidget;

/**
 * Wo gerade die Arbeit liegt — offene Tickets je Kunde.
 *
 * Nur für Administratoren: für einen Mitarbeiter mit zwei zugeordneten
 * Projekten hat die Verteilung über alle Kunden keine Aussage.
 */
class TicketsJeKunde extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Offene Tickets je Kunde';

    public static function canView(): bool
    {
        return auth()->user()?->istAdmin() ?? false;
    }

    protected function getData(): array
    {
        $kunden = Customer::query()
            ->aktiv()
            ->withCount(['tickets as offene' => fn ($q) => $q->offen()])
            // Kein having('offene', '>', 0): withCount erzeugt eine
            // Unterabfrage als Ausgabespalte, und Postgres lässt in HAVING
            // keinen solchen Alias zu — anders als MySQL, wo das durchginge.
            // ORDER BY darf ihn dagegen verwenden. Weil absteigend sortiert
            // wird, stehen die Nullen ohnehin hinten; sie fallen nach dem
            // Limit in PHP weg.
            ->orderByDesc('offene')
            ->limit(10)
            ->get()
            ->filter(fn (Customer $kunde) => $kunde->offene > 0)
            ->values();

        return [
            'datasets' => [[
                'label' => 'Offene Tickets',
                'data' => $kunden->pluck('offene')->all(),
                // Die Kundenfarbe, die auch in Listen und Badges steht —
                // damit dasselbe Balkenpaar überall wiedererkennbar bleibt.
                'backgroundColor' => $kunden->pluck('farbe')->all(),
                'borderColor' => $kunden->pluck('farbe')->all(),
            ]],
            'labels' => $kunden->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                // Ganze Tickets, keine halben: ohne stepSize zeichnet Chart.js
                // bei kleinen Zahlen 0,5er-Schritte auf die Achse.
                'y' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]],
            ],
        ];
    }
}
