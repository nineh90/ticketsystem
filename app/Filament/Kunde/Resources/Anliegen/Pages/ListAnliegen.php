<?php

namespace App\Filament\Kunde\Resources\Anliegen\Pages;

use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAnliegen extends ListRecords
{
    protected static string $resource = AnliegenResource::class;

    public function getTitle(): string
    {
        return 'Anliegen';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Neues Anliegen')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Vier Reiter, absteigend nach Dringlichkeit für den Kunden.
     *
     * "Sie sind am Zug" steht vorn und ist der einzige Reiter mit einem
     * roten Abzeichen — er ist das, wofür der Kunde sich anmeldet.
     */
    public function getTabs(): array
    {
        // Der Parameter muss $query heißen: Filament reicht die Abfrage über
        // den Namen hinein, nicht über den Typ. Heißt er anders, kommt ein
        // frisch gebauter Builder ohne Model aus dem Container — und der
        // kennt weder offen() noch wartetAufKunde().
        return [
            'am-zug' => Tab::make('Sie sind am Zug')
                ->icon('heroicon-m-hand-raised')
                ->badge($this->zaehlen(fn (Builder $query) => $query->wartetAufKunde()))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->wartetAufKunde()),

            'offen' => Tab::make('In Bearbeitung')
                ->icon('heroicon-m-arrow-path')
                ->badge($this->zaehlen(fn (Builder $query) => $query->offen()))
                ->modifyQueryUsing(fn (Builder $query) => $query->offen()),

            'erledigt' => Tab::make('Erledigt')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'status',
                    fn (Builder $s) => $s->where('ist_abschluss', true),
                )),

            'alle' => Tab::make('Alle')
                ->icon('heroicon-m-bars-3'),
        ];
    }

    /** Welcher Reiter beim Öffnen aktiv ist. */
    public function getDefaultActiveTab(): string|int|null
    {
        // Wenn etwas auf den Kunden wartet, soll er genau das zuerst sehen —
        // sonst die laufende Arbeit. Ein Reiter, der immer gleich anfängt,
        // wäre bequemer zu schreiben und würde den einen Fall verstecken, um
        // dessentwillen es die Reiter gibt.
        return static::getResource()::getEloquentQuery()->wartetAufKunde()->exists()
            ? 'am-zug'
            : 'offen';
    }

    private function zaehlen(callable $einschraenken): ?string
    {
        $anzahl = $einschraenken(static::getResource()::getEloquentQuery())->count();

        return $anzahl > 0 ? (string) $anzahl : null;
    }
}
