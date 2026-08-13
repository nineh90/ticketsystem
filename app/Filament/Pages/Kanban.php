<?php

namespace App\Filament\Pages;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Tickets als Spalten je Stadium, mit Ziehen und Ablegen.
 *
 * Bewusst selbst gebaut statt über ein Plugin: die Stadien sind bei uns
 * konfigurierbare Datensätze, nicht ein festes Enum, und die Sichtbarkeit muss
 * durch denselben Scope laufen wie überall sonst. Ein fertiges Kanban-Plugin
 * müsste man für beides ohnehin umbiegen.
 */
class Kanban extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $navigationLabel = 'Kanban';

    protected static ?string $title = 'Kanban';

    protected static ?int $navigationSort = 45;

    protected string $view = 'filament.pages.kanban';

    /** Optionaler Filter auf ein Projekt, aus der Adresszeile. */
    public ?int $projektId = null;

    public function mount(): void
    {
        $this->projektId = request()->integer('projekt') ?: null;
    }

    /** @return Collection<int, TicketStatus> */
    public function getStadien(): Collection
    {
        return TicketStatus::query()->sortiert()->get();
    }

    /** @return Collection<int, Project> */
    public function getProjekte(): Collection
    {
        return Project::query()
            ->sichtbarFuer(auth()->user())
            ->with('customer')
            ->get()
            ->sortBy(fn (Project $p) => $p->customer->name.$p->name)
            ->values();
    }

    /**
     * Alle sichtbaren Tickets, nach Stadium gruppiert.
     *
     * Eine Abfrage für das ganze Brett statt einer je Spalte — bei sieben
     * Stadien wären das sonst sieben Rundreisen zur Datenbank, plus je eine
     * für Kunde und Projekt jeder Karte.
     */
    public function getTicketsNachStadium(): Collection
    {
        return Ticket::query()
            ->sichtbarFuer(auth()->user())
            ->when($this->projektId, fn ($q) => $q->where('project_id', $this->projektId))
            ->with(['customer', 'project', 'zustaendig'])
            ->orderBy('position')
            ->orderByDesc('id')
            ->get()
            ->groupBy('ticket_status_id');
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
}
