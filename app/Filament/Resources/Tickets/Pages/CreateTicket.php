<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Concerns\NimmtDateienEntgegen;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicket extends CreateRecord
{
    use NimmtDateienEntgegen;

    protected static string $resource = TicketResource::class;

    /** Beim Betreten wegräumen, was andere im Zwischenlager gelassen haben. */
    protected function fillForm(): void
    {
        $this->zwischenlagerAufraeumen();

        parent::fillForm();
    }

    /**
     * "dateien" ist keine Spalte — das Feld wird hier aus den Daten genommen
     * und in afterCreate() verarbeitet, wenn es eine Ticketnummer gibt.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->dateienAusFormular($data);
    }

    protected function afterCreate(): void
    {
        $this->dateienAnhaengen($this->getRecord());
    }

    /**
     * Nach dem Anlegen ins Ticket, nicht zurück in die Liste.
     *
     * Wer gerade Dateien mitgeschickt hat, will sehen, dass sie angekommen
     * sind — in der Liste steht davon nichts.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
