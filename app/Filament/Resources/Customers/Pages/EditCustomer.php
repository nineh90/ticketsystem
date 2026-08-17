<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * Die Kundenakte offen zu haben heißt, die Meldungen dazu gesehen zu
     * haben.
     *
     * Betrifft heute genau eine Meldung: der Kunde hat seine Stammdaten
     * geändert (siehe Kunde\Pages\Profil). Sie führt hierher, und wer hier
     * steht, hat die neue Anschrift vor sich — sie danach noch einmal in der
     * Glocke wegzuklicken, wäre ein zweiter Handgriff für dieselbe Sache.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        Benachrichtigung::gesehen(auth()->user(), Herkunft::kunde($this->record->getKey()));
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
