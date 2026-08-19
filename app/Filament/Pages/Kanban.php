<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tickets als Spalten je Stadium, mit Ziehen und Ablegen.
 *
 * Bewusst selbst gebaut statt über ein Plugin: die Stadien sind bei uns
 * konfigurierbare Datensätze, nicht ein festes Enum, und die Sichtbarkeit muss
 * durch denselben Scope laufen wie überall sonst. Ein fertiges Kanban-Plugin
 * müsste man für beides ohnehin umbiegen.
 *
 * Die Vorauswahl steht in der Sitzung, nicht in der Adresse — wie die Filter
 * der Ticketliste. Ein `?projekt=` in der Adresse schlägt sie trotzdem, damit
 * ein weitergegebener Link zeigt, was der Absender gemeint hat.
 */
class Kanban extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    /**
     * "Deck" und nicht "Kanban".
     *
     * Der Name kam aus dem Gebrauch: "ich geh mal das Deck schrubben" heisst
     * Tickets abarbeiten. Ein Wort, das im Betrieb von selbst entsteht, ist
     * das bessere — "Kanban" beschreibt die Bauart des Bretts und nicht das,
     * was man daran tut.
     *
     * Die Klasse heisst weiter Kanban und die Adresse bleibt /kanban: das ist
     * die Bauart, und die aendert sich nicht mit der Beschriftung. Genauso
     * steht es bei Betrieb (angezeigt als "Bruecke") und MeinBereich
     * ("Meine Wache").
     */
    protected static ?string $navigationLabel = 'Deck';

    protected static ?string $title = 'Deck';

    protected static ?int $navigationSort = 45;

    protected string $view = 'filament.pages.kanban';

    /**
     * Wie viele Karten eine abschließende Spalte höchstens zeigt.
     *
     * Erledigtes sammelt sich für immer an. Eine Spalte mit sechzig Karten
     * ist keine Übersicht, sondern ein Archiv — und sie macht das ganze Brett
     * so hoch, dass man an die waagerechte Bildlaufleiste nicht mehr
     * herankommt. Die offenen Spalten bleiben vollständig: dort ist jede
     * Karte etwas, das noch jemand anfassen muss.
     */
    private const KARTEN_JE_ABSCHLUSS_SPALTE = 15;

    public ?int $kundeId = null;

    public ?int $projektId = null;

    public bool $nurMeine = false;

    public function mount(): void
    {
        $gemerkt = session()->get($this->vorauswahlSchluessel(), []);

        // Die Adresse schlägt die Sitzung, aber nur wenn sie etwas sagt.
        $this->projektId = request()->integer('projekt') ?: ($gemerkt['projekt'] ?? null);
        $this->kundeId = $gemerkt['kunde'] ?? null;
        $this->nurMeine = (bool) ($gemerkt['nurMeine'] ?? false);

        // Ein Projekt aus der Adresse zieht seinen Kunden mit, sonst stünde
        // im Kundenfeld etwas anderes als im Projektfeld.
        if ($this->projektId) {
            $this->kundeId = Project::query()->whereKey($this->projektId)->value('customer_id');
        }

        $this->vorauswahlPruefen();
    }

    public function updatedKundeId(): void
    {
        // Ein Projekt, das nicht zum neuen Kunden gehört, ergäbe zusammen mit
        // ihm eine leere Ansicht ohne erkennbaren Grund.
        $this->projektId = null;

        $this->vorauswahlMerken();
    }

    public function updatedProjektId(): void
    {
        $this->vorauswahlMerken();
    }

    public function updatedNurMeine(): void
    {
        $this->vorauswahlMerken();
    }

    public function zuruecksetzen(): void
    {
        $this->kundeId = null;
        $this->projektId = null;
        $this->nurMeine = false;

        $this->vorauswahlMerken();
    }

    public function hatVorauswahl(): bool
    {
        return $this->kundeId !== null || $this->projektId !== null || $this->nurMeine;
    }

    /** @return Collection<int, TicketStatus> */
    public function getStadien(): Collection
    {
        return TicketStatus::query()->sortiert()->get();
    }

    /** @return Collection<int, Customer> */
    public function getKunden(): Collection
    {
        return Customer::query()
            ->sichtbarFuer(auth()->user())
            ->orderBy('name')
            ->get();
    }

    /**
     * Die Projekte zur Auswahl — beim gewählten Kunden nur dessen eigene.
     *
     * @return Collection<int, Project>
     */
    public function getProjekte(): Collection
    {
        return Project::query()
            ->sichtbarFuer(auth()->user())
            ->when($this->kundeId, fn (Builder $q) => $q->where('customer_id', $this->kundeId))
            ->with('customer')
            ->get()
            ->sortBy(fn (Project $p) => $p->customer->name.$p->name)
            ->values();
    }

    /**
     * Das Brett, Spalte für Spalte.
     *
     * Gibt je Stadium schon alles zurück, was die Ansicht braucht — Karten,
     * Gesamtzahl und wie viele davon nicht gezeigt werden. Die Rechnerei
     * gehört nicht ins Blade: dort stünde sie zwischen zwei Schleifen und
     * würde bei jedem Umbau der Ansicht neu erfunden.
     *
     * @return Collection<int, object{stadium: TicketStatus, karten: Collection<int, Ticket>, gesamt: int, verborgen: int}>
     */
    public function getSpalten(): Collection
    {
        $stadien = $this->getStadien();

        // Alle Zahlen in einer Abfrage. Sie müssen auch für die Karten
        // stimmen, die gar nicht geladen werden — sonst stünde über einer
        // gekappten Spalte die gekappte Zahl, und das Brett behauptete, es
        // gäbe fünfzehn Erledigte.
        $anzahlen = $this->basis()
            ->toBase()
            ->selectRaw('ticket_status_id, count(*) as anzahl')
            ->groupBy('ticket_status_id')
            ->pluck('anzahl', 'ticket_status_id');

        $offene = $this->karten($stadien->where('ist_abschluss', false));

        return $stadien->map(function (TicketStatus $stadium) use ($anzahlen, $offene) {
            $gesamt = (int) ($anzahlen[$stadium->id] ?? 0);

            $karten = $stadium->ist_abschluss
                ? $this->karten(collect([$stadium]), self::KARTEN_JE_ABSCHLUSS_SPALTE)->get($stadium->id, collect())
                : $offene->get($stadium->id, collect());

            return (object) [
                'stadium' => $stadium,
                'karten' => $karten,
                'gesamt' => $gesamt,
                'verborgen' => max(0, $gesamt - $karten->count()),
            ];
        });
    }

    /**
     * Wohin "… und N weitere" führt: dieselbe Menge, nur als Liste.
     *
     * Über den Reiter "Alle" und nicht "Erledigt": ein abschließendes Stadium
     * ohne erledigt_at käme sonst nicht vor, und genau die will man sehen,
     * wenn man dieser Zahl nachgeht.
     */
    public function listeFuerStadium(TicketStatus $stadium): string
    {
        return TicketResource::listeUrl('alle', weitereFilter: array_filter([
            'ticket_status_id' => ['values' => [$stadium->id]],
            'project' => $this->projektId ? ['value' => $this->projektId] : null,
            // Der Kunde nur, wenn kein Projekt gewählt ist — sonst schränken
            // zwei Filter dieselbe Menge ein und der Kundenfilter steht als
            // Abzeichen daneben, ohne etwas zu tun.
            'customer' => (! $this->projektId && $this->kundeId) ? ['value' => $this->kundeId] : null,
            'assigned_to' => $this->nurMeine ? ['value' => auth()->id()] : null,
        ]));
    }

    /**
     * Eine Karte wurde abgelegt.
     *
     * @param  array<int, int|string>  $reihenfolge  Ticket-IDs in neuer Reihenfolge
     */
    public function verschieben(int $ticketId, int $stadiumId, array $reihenfolge = []): void
    {
        $ticket = Ticket::query()
            ->sichtbarFuer(auth()->user())
            ->findOrFail($ticketId);

        // Nicht auf die Sichtbarkeit allein verlassen: wer ein Ticket sehen
        // darf, darf es nicht zwangsläufig ändern.
        $this->authorize('update', $ticket);

        $ticket->update(['ticket_status_id' => $stadiumId]);

        // Reihenfolge innerhalb der Zielspalte festschreiben.
        foreach (array_values($reihenfolge) as $platz => $id) {
            Ticket::query()->whereKey($id)->update(['position' => $platz]);
        }
    }

    /**
     * Die Karten der angegebenen Stadien, nach Stadium gruppiert.
     *
     * Ohne Grenze eine Abfrage für alle offenen Spalten zusammen — bei sieben
     * Stadien wären es sonst sieben Rundreisen, plus je eine für Kunde und
     * Projekt jeder Karte. Mit Grenze geht es nur je Spalte einzeln, weil ein
     * LIMIT über die ganze Menge die Karten dorthin verteilte, wo zufällig
     * die neuesten liegen.
     *
     * @param  Collection<int, TicketStatus>  $stadien
     * @return Collection<int, Collection<int, Ticket>>
     */
    private function karten(Collection $stadien, ?int $grenze = null): Collection
    {
        if ($stadien->isEmpty()) {
            return collect();
        }

        return $this->basis()
            ->whereIn('ticket_status_id', $stadien->pluck('id'))
            ->with(['customer', 'project', 'zustaendig'])
            ->orderBy('position')
            ->orderByDesc('id')
            ->when($grenze, fn (Builder $q) => $q->limit($grenze))
            ->get()
            ->groupBy('ticket_status_id');
    }

    /** Die Menge, über die das ganze Brett spricht — einmal definiert. */
    private function basis(): Builder
    {
        return Ticket::query()
            ->sichtbarFuer(auth()->user())
            ->when($this->kundeId, fn (Builder $q) => $q->where('customer_id', $this->kundeId))
            ->when($this->projektId, fn (Builder $q) => $q->where('project_id', $this->projektId))
            ->when($this->nurMeine, fn (Builder $q) => $q->where('assigned_to', auth()->id()));
    }

    /**
     * Eine gemerkte Vorauswahl kann veraltet sein — ein Projekt wurde
     * gelöscht, eine Zuordnung zurückgenommen. Dann stünde das Brett leer da
     * und im Auswahlfeld nichts Passendes; es sähe aus, als gäbe es keine
     * Tickets mehr.
     */
    private function vorauswahlPruefen(): void
    {
        $nutzer = auth()->user();

        if ($this->kundeId && ! Customer::query()->sichtbarFuer($nutzer)->whereKey($this->kundeId)->exists()) {
            $this->kundeId = null;
            $this->projektId = null;
        }

        if ($this->projektId && ! Project::query()->sichtbarFuer($nutzer)->whereKey($this->projektId)->exists()) {
            $this->projektId = null;
        }
    }

    private function vorauswahlMerken(): void
    {
        session()->put($this->vorauswahlSchluessel(), [
            'kunde' => $this->kundeId,
            'projekt' => $this->projektId,
            'nurMeine' => $this->nurMeine,
        ]);

        // Das Brett wieder nach links. Wer weit rechts stand und dann einen
        // Kunden wählt, sähe sonst dessen leere Abschlussspalten und hielte
        // die Auswahl für kaputt — die Karten stehen links, außerhalb des
        // Sichtfelds.
        $this->dispatch('kanban-gefiltert');
    }

    private function vorauswahlSchluessel(): string
    {
        return static::class.'_vorauswahl';
    }
}
