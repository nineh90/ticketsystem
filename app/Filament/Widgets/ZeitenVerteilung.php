<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Sichtbarkeit;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Wohin die Zeit geflossen ist.
 *
 * Das Gegenstück zu TicketsVerteilung: dort steht, wo die Arbeit *liegt*,
 * hier, wo sie bereits *hingegangen* ist. Beide Fragen sehen von außen
 * ähnlich aus und werden im Alltag ständig verwechselt — ein Kunde mit drei
 * offenen Tickets kann derjenige sein, der die wenigste Zeit gekostet hat.
 * Deshalb stehen die Diagramme nebeneinander und nicht als zwei Reihen
 * desselben Bildes.
 *
 * Die Aufteilung folgt bewusst genau der von TicketsVerteilung: für den
 * Administrator die Summen je Kunde, für alle anderen die je Projekt. Der
 * Grund ist derselbe — ein Mitarbeiter, der einem einzigen Kunden zugeordnet
 * ist, bekäme sonst ein Diagramm aus einem Balken, das ihm nichts sagt. Und
 * zwei Diagramme auf derselben Seite, die verschieden gruppieren, liest man
 * einmal falsch und danach gar nicht mehr.
 *
 * Was ein Mitarbeiter dabei sieht, entscheidet nicht dieses Widget, sondern
 * Ticket::sichtbarFuer — dieselbe Abfrage wie überall sonst. Gezählt wird
 * darin die Zeit *aller* Beteiligten, nicht nur die eigene: wer das Ticket
 * sieht, sieht auch, wer wie lange daran saß (siehe TimeEntry::sichtbarFuer).
 * Die eigene Wochenzeit steht davon unberührt in MeinUeberblick.
 */
class ZeitenVerteilung extends ChartWidget
{
    /** Direkt hinter TicketsVerteilung. */
    protected static ?int $sort = 6;

    /**
     * Volle Breite, anders als beim Diagramm darüber.
     *
     * Nicht aus Geschmack: in der Reihe darüber steht der Ereignisstrom, und
     * der ist hoch. Ein halb breites Diagramm darunter bekäme die nächste
     * Reihe für sich allein und ließe die halbe Seite daneben leer. Dazu
     * kommt, was die Balken tragen müssen — "KEIN EINZELFALL e.V." neben
     * "Sarah Schweikert" steht auf halber Breite schräg und abgeschnitten.
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * Gedeckelt, weil Chart.js sonst die Höhe aus der Breite ableitet: über
     * die ganze Seite wird das Diagramm einen halben Bildschirm hoch, und
     * drei Balken, für die man scrollen muss, sind keine Übersicht mehr.
     * Ungefähr die Höhe des Diagramms darüber, damit die Seite eine Linie
     * behält.
     */
    protected ?string $maxHeight = '280px';

    protected ?string $emptyStateHeading = 'Keine Zeiten erfasst';

    /**
     * Der Text muss beide Fälle tragen: noch nie Zeit gebucht, und im
     * gewählten Zeitraum keine. Von außen sind sie nicht zu unterscheiden,
     * und der Zeitraum steht als Auswahl daneben — "Sobald jemand Zeit
     * bucht" wäre bei "Letzter Monat" schlicht falsch.
     */
    protected ?string $emptyStateDescription = 'Im gewählten Zeitraum ist auf keinem Ticket Zeit gebucht.';

    /**
     * Vorgabe "Gesamt", weil das die Frage ist, die man an ein Diagramm über
     * Kundenzeiten zuerst stellt: was hat dieser Kunde uns insgesamt
     * gekostet. Die Zeiträume darunter beantworten die zweite — ob sich das
     * gerade ändert.
     */
    public ?string $filter = 'gesamt';

    /**
     * Ohne Zuordnung gibt es nichts zu verteilen. Dieselbe Bedingung wie bei
     * TicketsVerteilung: die beiden erscheinen und verschwinden gemeinsam,
     * sonst stünde eines allein in einer halben Reihe.
     */
    public static function canView(): bool
    {
        return auth()->check() && ! Sichtbarkeit::ohneProjekte();
    }

    public function getHeading(): ?string
    {
        return $this->alsAdmin()
            ? 'Erfasste Zeit je Kunde'
            : 'Erfasste Zeit je Projekt';
    }

    /** @return array<string, string> */
    protected function getFilters(): ?array
    {
        return [
            'gesamt' => 'Gesamt',
            'jahr' => 'Dieses Jahr',
            'monat' => 'Dieser Monat',
            'letzter-monat' => 'Letzter Monat',
        ];
    }

    protected function getData(): array
    {
        $eintraege = $this->alsAdmin() ? $this->jeKunde() : $this->jeProjekt();

        // Ganz leer und nicht bloß ohne Balken: Filament setzt den Leertext
        // nur, wenn getData() nichts zurückgibt (ChartWidget::isEmpty). Ein
        // Datensatz mit leerer Reihe geht als "Daten vorhanden" durch, und
        // im Diagramm steht dann eine Achse von 0 bis 1 h ohne einen
        // einzigen Balken — bei "Letzter Monat" der Normalfall.
        if ($eintraege->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Erfasste Zeit',
                // Stunden als voller Bruch, nicht gerundet: das Diagramm
                // rechnet für die Beschriftung wieder auf Minuten zurück
                // (siehe getOptions), und aus einer auf zwei Stellen
                // gerundeten Stunde wird dabei "2:14 h" statt "2:15 h".
                //
                // Die Umwandlung in float ist keine Förmlichkeit: PHP gibt
                // bei glatt aufgehender Division einen int zurück, sodass in
                // derselben Reihe 1 neben 1.5 stünde — im JSON und damit in
                // jeder Prüfung ein anderer Typ, je nach Datenlage.
                'data' => $eintraege->map(fn (object $e) => (float) ($e->minuten / 60))->all(),
                'backgroundColor' => $eintraege->pluck('farbe')->all(),
                'borderColor' => $eintraege->pluck('farbe')->all(),
            ]],
            'labels' => $eintraege->pluck('titel')->all(),
        ];
    }

    /** @return Collection<int, object{titel: string, minuten: int, farbe: ?string}> */
    private function jeKunde(): Collection
    {
        $minuten = $this->minutenJe('tickets.customer_id');

        return Customer::query()
            ->aktiv()
            ->whereKey($minuten->keys())
            ->get()
            ->map(fn (Customer $kunde) => (object) [
                'titel' => $kunde->name,
                'minuten' => (int) $minuten[$kunde->getKey()],
                'farbe' => $kunde->farbe,
            ])
            ->sortByDesc('minuten')
            ->take(10)
            ->values();
    }

    /** @return Collection<int, object{titel: string, minuten: int, farbe: ?string}> */
    private function jeProjekt(): Collection
    {
        $minuten = $this->minutenJe('tickets.project_id');

        return Project::query()
            ->whereKey($minuten->keys())
            ->with('customer')
            ->get()
            ->map(fn (Project $projekt) => (object) [
                // Kürzel davor, wie im Diagramm daneben: Projektnamen
                // wiederholen sich zwischen Kunden.
                'titel' => $projekt->customer->kuerzel.' — '.$projekt->name,
                'minuten' => (int) $minuten[$projekt->getKey()],
                'farbe' => $projekt->farbe ?: $projekt->customer->farbe,
            ])
            ->sortByDesc('minuten')
            ->take(10)
            ->values();
    }

    /**
     * Gebuchte Minuten, gruppiert nach der übergebenen Spalte der Tickets.
     *
     * Die Abfrage geht von den Tickets aus und nicht von den Zeiten, damit
     * sichtbarFuer greifen kann — es ist die eine Stelle, an der im ganzen
     * System entschieden wird, wer welches Ticket sieht. Über die Zeiten
     * herum wäre die Rollentrennung hier neu zu formulieren, und eine zweite
     * Formulierung derselben Regel ist die, die beim nächsten Umbau nicht
     * mitwandert.
     *
     * Laufende Uhren zählen mit 0 mit: "minuten" wird erst beim Stoppen
     * geschrieben. Das ist dieselbe Rechnung wie in TeamUeberblick und für
     * eine Verteilung über Wochen und Monate ohne Belang — die angefangene
     * Stunde landet im Diagramm, sobald sie gebucht ist.
     *
     * @return Collection<int, int> Minuten je Schlüssel
     */
    private function minutenJe(string $spalte): Collection
    {
        /** @var User $nutzer */
        $nutzer = auth()->user();

        [$von, $bis] = $this->zeitraum();

        return Ticket::query()
            ->sichtbarFuer($nutzer)
            ->join('time_entries', 'time_entries.ticket_id', '=', 'tickets.id')
            ->when($von, fn ($q) => $q->where('time_entries.gestartet_am', '>=', $von))
            ->when($bis, fn ($q) => $q->where('time_entries.gestartet_am', '<', $bis))
            ->groupBy($spalte)
            ->selectRaw($spalte.' as schluessel, sum(time_entries.minuten) as minuten')
            // Nullen fallen hier schon weg: ein Kunde ohne gebuchte Zeit
            // gehört nicht in eine Verteilung der Zeit. Anders als beim
            // Ticketdiagramm geht das in HAVING, weil hier eine echte
            // Aggregatfunktion steht und keine Unterabfrage als Alias.
            ->havingRaw('sum(time_entries.minuten) > 0')
            ->pluck('minuten', 'schluessel');
    }

    /**
     * Anfang und Ende des gewählten Zeitraums, je null für "offen".
     *
     * Das Ende ist ausschließend gemeint (< statt <=), weil gestartet_am ein
     * Zeitpunkt ist und kein Datum: mit <= fiele der letzte Tag eines Monats
     * bis auf die Buchungen um Punkt Mitternacht heraus.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function zeitraum(): array
    {
        return match ($this->filter) {
            'jahr' => [now()->startOfYear(), null],
            'monat' => [now()->startOfMonth(), null],
            'letzter-monat' => [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->startOfMonth(),
            ],
            default => [null, null],
        };
    }

    private function alsAdmin(): bool
    {
        return auth()->user()?->istAdmin() ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Als RawJs, weil Stunden als Dezimalzahl niemand liest: "2,25 h" ist
     * eine Rechenaufgabe, "2:15 h" eine Uhrzeit. Die Umrechnung steht damit
     * ein zweites Mal im System — die PHP-Fassung ist Dauer::alsStunden — und
     * das ist hier der bessere Handel: die Alternative wäre, die fertigen
     * Texte je Balken durchzureichen, was Chart.js beim Sortieren und
     * Skalieren nicht helfen würde.
     */
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
