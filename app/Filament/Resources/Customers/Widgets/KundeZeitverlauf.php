<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\Customer;
use App\Models\TimeEntry;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Wie viel Zeit dieser Kunde Monat für Monat gekostet hat.
 *
 * Die Gesamtzahl steht schon als Kachel darüber. Sie beantwortet aber nicht
 * die Frage, die man bei einem Kunden tatsächlich hat: wird es mehr oder
 * weniger. Ein Betreuungskunde mit gleichmäßig zwei Stunden im Monat und
 * einer, bei dem seit dem Relaunch nichts mehr passiert, haben dieselbe
 * Summe und heißen zwei verschiedene Gespräche.
 *
 * Zwölf Monate fest, auch wenn erst drei Daten haben: eine Achse, die mit
 * der Datenlage wächst, ändert bei jedem Aufruf ihren Maßstab, und dann
 * sieht ein ruhiger Monat neben zwei leeren aus wie ein Ausschlag.
 */
class KundeZeitverlauf extends ChartWidget
{
    public ?Model $record = null;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Erfasste Zeit je Monat';

    protected ?string $description = 'Die letzten zwölf Monate.';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Noch keine Zeiten';

    protected ?string $emptyStateDescription = 'Sobald an einem Ticket dieses Kunden Zeit gebucht wird, steht sie hier.';

    protected function getData(): array
    {
        /** @var Customer $kunde */
        $kunde = $this->record;

        $monate = $this->monate();

        // Eine Abfrage für alle zwölf Monate statt zwölf einzelner. Gruppiert
        // wird über das abgeschnittene Datum; date_trunc ist Postgres-eigen,
        // und das ist hier in Ordnung — die Anwendung läuft überall auf
        // Postgres (siehe README).
        $gebucht = TimeEntry::query()
            ->whereIn('ticket_id', $kunde->tickets()->select('tickets.id'))
            ->where('gestartet_am', '>=', $monate->first())
            ->groupByRaw("date_trunc('month', gestartet_am)")
            ->selectRaw("date_trunc('month', gestartet_am) as monat, sum(minuten) as minuten")
            ->pluck('minuten', 'monat')
            // Postgres gibt den Schlüssel als vollen Zeitstempel zurück
            // ("2026-08-01 00:00:00"); vergleichbar wird er erst als
            // Jahr-Monat.
            ->mapWithKeys(fn ($minuten, $monat) => [
                Carbon::parse($monat)->format('Y-m') => (int) $minuten,
            ]);

        if ($gebucht->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Erfasste Zeit',
                'data' => $monate
                    ->map(fn (Carbon $m) => (float) (($gebucht[$m->format('Y-m')] ?? 0) / 60))
                    ->all(),
                'backgroundColor' => $kunde->farbe,
                'borderColor' => $kunde->farbe,
            ]],
            'labels' => $monate->map(fn (Carbon $m) => $m->translatedFormat('M y'))->all(),
        ];
    }

    /**
     * Die zwölf Monate der Achse, ältester zuerst.
     *
     * startOfMonth vor subMonths, sonst rutscht der 31. auf einen Monat mit
     * 30 Tagen und die Reihe hat einen Monat doppelt.
     *
     * @return Collection<int, Carbon>
     */
    private function monate(): Collection
    {
        $start = now()->startOfMonth()->subMonthsNoOverflow(11);

        return collect(range(0, 11))
            ->map(fn (int $i) => $start->copy()->addMonthsNoOverflow($i));
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** Wie in ZeitenVerteilung: Stunden als Uhrzeit, nicht als Dezimalzahl. */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (kontext) => {
                                const minuten = Math.round(kontext.parsed.y * 60);

                                return Math.floor(minuten / 60)
                                    + ':' + String(minuten % 60).padStart(2, '0') + ' h';
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: (wert) => wert + ' h' },
                    },
                },
            }
        JS);
    }
}
