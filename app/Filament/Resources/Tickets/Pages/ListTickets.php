<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Der zuletzt gewählte Reiter, für die Dauer der Sitzung.
     *
     * Filter, Suche und Sortierung hält Filament selbst in der Sitzung
     * (siehe TicketsTable) — den Reiter nicht, und der ist die sichtbarste
     * Einstellung von allen. Ohne das hier stünde man nach jedem Ausflug ins
     * Dashboard wieder auf "Offen", obwohl man den ganzen Vormittag unter
     * "Meine" gearbeitet hat.
     */
    public function getDefaultActiveTab(): string|int|null
    {
        $gemerkt = session()->get($this->reiterSchluessel());

        // Ein Reiter, den es nicht mehr gibt, führt zu einer Liste ohne
        // Einschränkung und ohne markierten Reiter — also lieber zurück auf
        // den ersten. Das kostet einmal nach einer Umbenennung eine falsche
        // Voreinstellung statt dauerhaft eine kaputte Ansicht.
        return is_string($gemerkt) && array_key_exists($gemerkt, $this->getCachedTabs())
            ? $gemerkt
            : parent::getDefaultActiveTab();
    }

    public function mount(): void
    {
        parent::mount();

        // Wer über eine Zahl hereinkommt, soll genau die Menge sehen, die
        // darauf stand — und nicht zusätzlich die Suche von heute Vormittag.
        // Reiter und Filter bringt die Adresse selbst mit, die Suche muss
        // hier weg (siehe TicketResource::listeUrl).
        if (request()->boolean('frisch')) {
            $this->tableSearch = '';
            $this->tableColumnSearches = [];

            session()->put($this->getTableSearchSessionKey(), '');
            session()->put($this->getTableColumnSearchesSessionKey(), []);
        }

        // Auch beim Ankommen merken, nicht erst beim Klicken: wer über eine
        // Dashboard-Kachel auf "Alle" landet, will beim nächsten Aufruf dort
        // weitermachen und nicht wieder auf "Offen" stehen.
        $this->reiterMerken();
    }

    /**
     * Livewire ruft das nach jedem Reiterwechsel.
     *
     * Der Elternaufruf muss bleiben: er setzt die Seitenzahl zurück und baut
     * den Spaltenmanager neu. Ohne ihn steht man nach dem Wechsel auf Seite 4
     * einer Liste, die nur zwei Seiten hat.
     */
    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        $this->reiterMerken();
    }

    private function reiterMerken(): void
    {
        session()->put($this->reiterSchluessel(), $this->activeTab);
    }

    private function reiterSchluessel(): string
    {
        return static::class.'_aktiver_reiter';
    }

    /**
     * Die Ansichten, die man täglich braucht — als Reiter über der Liste.
     *
     * Vorher steckten dieselben Einschränkungen als Schalter im
     * Filtermenü hinter dem Trichter. Das war zwar möglich, aber unsichtbar:
     * wer "nur meine Tickets" sehen will, klickt erst ein Menü auf, sucht
     * einen Schalter und schließt das Menü wieder — für den häufigsten
     * Handgriff des Tages drei Klicks an einer Stelle, die man kennen muss.
     *
     * Die Zahl am Reiter ist ausdrücklich Teil der Sache: sie beantwortet
     * "habe ich etwas Überfälliges?", ohne dass man den Reiter überhaupt
     * anklickt.
     */
    public function getTabs(): array
    {
        // Der Parameter muss $query heißen. Filament reicht die Abfrage unter
        // diesem Namen hinein; heißt er anders, greift die Auflösung über den
        // Typ — und die holt sich einen frisch gebauten Builder aus dem
        // Container, der zu keinem Model gehört. Das Ergebnis war ein
        // "Call to undefined method Builder::offen()" beim Aufruf der Liste.
        return [
            'offen' => Tab::make('Offen')
                ->icon('heroicon-m-inbox')
                ->badge($this->zaehlen(fn (Builder $query) => $query->offen()))
                ->modifyQueryUsing(fn (Builder $query) => $query->offen()),

            'meine' => Tab::make('Meine')
                ->icon('heroicon-m-user')
                ->badge($this->zaehlen(fn (Builder $query) => $query->offen()->where('assigned_to', auth()->id())))
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->offen()->where('assigned_to', auth()->id())),

            'ueberfaellig' => Tab::make('Überfällig')
                ->icon('heroicon-m-exclamation-triangle')
                ->badge($this->zaehlen(fn (Builder $query) => $query->ueberfaellig()))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->ueberfaellig()),

            // Seit es den Kundenbereich gibt: was von draußen hereinkommt.
            // Der eigene Reiter, weil auf der anderen Seite jemand wartet —
            // ein Kundenanliegen, das drei Tage im Backlog liegt, ist etwas
            // anderes als eine eigene Notiz, die dort drei Tage liegt.
            'von-kunden' => Tab::make('Von Kunden')
                ->icon('heroicon-m-inbox-arrow-down')
                ->badge($this->zaehlen(fn (Builder $query) => $query->offen()->vomKunden()))
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->offen()->vomKunden()),

            'unzugewiesen' => Tab::make('Unzugewiesen')
                ->icon('heroicon-m-question-mark-circle')
                ->badge($this->zaehlen(fn (Builder $query) => $query->offen()->whereNull('assigned_to')))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->offen()->whereNull('assigned_to')),

            'erledigt' => Tab::make('Erledigt')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('erledigt_at')),

            // Ohne diesen Reiter käme man an ein erledigtes Ticket, das nie
            // ein erledigt_at bekommen hat, überhaupt nicht mehr heran.
            'alle' => Tab::make('Alle')
                ->icon('heroicon-m-bars-3'),
        ];
    }

    /**
     * Zählt auf der Abfrage der Ressource — also mit derselben
     * Sichtbarkeitsregel wie die Liste darunter. Eine Zahl am Reiter, die
     * fremde Tickets mitzählt, wäre schlimmer als keine.
     */
    private function zaehlen(callable $einschraenken): ?string
    {
        $anzahl = $einschraenken(TicketResource::getEloquentQuery())->count();

        // Kein Abzeichen für "0" — eine Null in einem Abzeichen liest sich wie
        // eine Meldung, dabei ist sie das Gegenteil davon.
        return $anzahl > 0 ? (string) $anzahl : null;
    }
}
