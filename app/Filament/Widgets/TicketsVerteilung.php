<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Support\Sichtbarkeit;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Wo gerade die Arbeit liegt.
 *
 * Für den Administrator die Verteilung über die Kunden, für alle anderen die
 * über ihre Projekte. Der Unterschied ist kein Schmuck: ein Mitarbeiter sieht
 * ohnehin nur einen Ausschnitt, und eine Kundenverteilung, die bei ihm aus
 * einem einzigen Balken besteht, sagt nichts. Die Projekte darunter sind
 * genau die Ebene, auf der er seine Arbeit verteilt sieht.
 */
class TicketsVerteilung extends ChartWidget
{
    protected static ?int $sort = 5;

    /**
     * Ohne Zuordnung gibt es nichts zu verteilen — dann bleibt das Diagramm
     * weg, statt eine leere Achse zu zeigen. Die Erklärung dazu steht schon
     * oben in MeinUeberblick.
     */
    public static function canView(): bool
    {
        return auth()->check() && ! Sichtbarkeit::ohneProjekte();
    }

    public function getHeading(): ?string
    {
        return $this->alsAdmin()
            ? 'Offene Tickets je Kunde'
            : 'Offene Tickets je Projekt';
    }

    protected function getData(): array
    {
        $eintraege = $this->alsAdmin() ? $this->jeKunde() : $this->jeProjekt();

        return [
            'datasets' => [[
                'label' => 'Offene Tickets',
                'data' => $eintraege->pluck('offene')->all(),
                // Die Kundenfarbe, die auch in Listen und Badges steht —
                // damit dasselbe Balkenpaar überall wiedererkennbar bleibt.
                // Bei der Projektansicht tragen alle Projekte desselben Kunden
                // dieselbe Farbe; das gruppiert sie ohne zweite Achse.
                'backgroundColor' => $eintraege->pluck('farbe')->all(),
                'borderColor' => $eintraege->pluck('farbe')->all(),
            ]],
            'labels' => $eintraege->pluck('titel')->all(),
        ];
    }

    /** @return Collection<int, object{titel: string, offene: int, farbe: ?string}> */
    private function jeKunde(): Collection
    {
        return Customer::query()
            ->aktiv()
            ->withCount(['tickets as offene' => fn (Builder $q) => $q->offen()])
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
            ->map(fn (Customer $kunde) => (object) [
                'titel' => $kunde->name,
                'offene' => $kunde->offene,
                'farbe' => $kunde->farbe,
            ])
            ->values();
    }

    /** @return Collection<int, object{titel: string, offene: int, farbe: ?string}> */
    private function jeProjekt(): Collection
    {
        /** @var User $nutzer */
        $nutzer = auth()->user();

        return Project::query()
            ->sichtbarFuer($nutzer)
            ->with('customer')
            ->withCount(['tickets as offene' => fn (Builder $q) => $q->offen()])
            ->orderByDesc('offene')
            ->limit(10)
            ->get()
            ->filter(fn (Project $projekt) => $projekt->offene > 0)
            ->map(fn (Project $projekt) => (object) [
                // Kürzel davor, weil Projektnamen zwischen Kunden doppelt
                // vorkommen können — zweimal "Webseite" nebeneinander wäre
                // nicht zuzuordnen.
                'titel' => $projekt->customer->kuerzel.' — '.$projekt->name,
                'offene' => $projekt->offene,
                // Projekte tragen selten eine eigene Farbe; der Kunde immer.
                'farbe' => $projekt->farbe ?: $projekt->customer->farbe,
            ])
            ->values();
    }

    private function alsAdmin(): bool
    {
        return auth()->user()?->istAdmin() ?? false;
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
