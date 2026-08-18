<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\Customer;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Was hereinkam und was erledigt wurde — Monat für Monat.
 *
 * Zwei Reihen und nicht eine, weil erst der Abstand zwischen ihnen etwas
 * sagt. Eine Reihe "eingegangen" allein zeigt, wie viel gemeldet wurde; erst
 * daneben "erledigt" zeigt, ob wir hinterherkommen. Zwei Monate, in denen
 * die zweite Reihe unter der ersten bleibt, sind der Punkt, an dem man mit
 * dem Kunden über Umfang reden muss — und das sieht man in keiner
 * Ticketliste.
 *
 * Gezählt wird nach Datum des Ereignisses, nicht nach heutigem Zustand: ein
 * Ticket vom März, das im Mai erledigt wurde, steht im März in der einen und
 * im Mai in der anderen Reihe. Deshalb sind die Summen der beiden Reihen
 * auch nicht gleich, und das ist richtig so.
 */
class KundeTicketaufkommen extends ChartWidget
{
    public ?Model $record = null;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Tickets je Monat';

    protected ?string $description = 'Eingegangen und erledigt, letzte zwölf Monate.';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Noch keine Tickets';

    protected ?string $emptyStateDescription = 'Sobald für diesen Kunden Tickets angelegt werden, steht der Verlauf hier.';

    protected function getData(): array
    {
        /** @var Customer $kunde */
        $kunde = $this->record;

        $monate = $this->monate();
        $ab = $monate->first();

        $eingegangen = $this->jeMonat($kunde, 'created_at', $ab);
        $erledigt = $this->jeMonat($kunde, 'erledigt_at', $ab);

        if ($eingegangen->isEmpty() && $erledigt->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Eingegangen',
                    'data' => $monate->map(fn (Carbon $m) => $eingegangen[$m->format('Y-m')] ?? 0)->all(),
                    'backgroundColor' => '#00bcd4',
                    'borderColor' => '#00bcd4',
                ],
                [
                    'label' => 'Erledigt',
                    'data' => $monate->map(fn (Carbon $m) => $erledigt[$m->format('Y-m')] ?? 0)->all(),
                    'backgroundColor' => '#22c55e',
                    'borderColor' => '#22c55e',
                ],
            ],
            'labels' => $monate->map(fn (Carbon $m) => $m->translatedFormat('M y'))->all(),
        ];
    }

    /**
     * Tickets je Monat, gezählt über die angegebene Zeitspalte.
     *
     * Der Spaltenname kommt nicht von außen, sondern aus den zwei Aufrufen
     * oben — er wandert in rohes SQL, und ein von außen gesetzter Wert wäre
     * hier die offene Tür.
     *
     * @return Collection<string, int>
     */
    private function jeMonat(Customer $kunde, string $spalte, Carbon $ab): Collection
    {
        return $kunde->tickets()
            ->whereNotNull($spalte)
            ->where($spalte, '>=', $ab)
            ->groupByRaw("date_trunc('month', {$spalte})")
            ->selectRaw("date_trunc('month', {$spalte}) as monat, count(*) as anzahl")
            ->pluck('anzahl', 'monat')
            ->mapWithKeys(fn ($anzahl, $monat) => [
                Carbon::parse($monat)->format('Y-m') => (int) $anzahl,
            ]);
    }

    /** @return Collection<int, Carbon> */
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

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => [
                // Ganze Tickets, keine halben — wie bei TicketsVerteilung.
                'y' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]],
            ],
        ];
    }
}
