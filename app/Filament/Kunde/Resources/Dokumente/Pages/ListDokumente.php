<?php

namespace App\Filament\Kunde\Resources\Dokumente\Pages;

use App\Filament\Kunde\Resources\Dokumente\DokumentResource;
use Filament\Resources\Pages\ListRecords;

class ListDokumente extends ListRecords
{
    protected static string $resource = DokumentResource::class;

    public function getTitle(): string
    {
        return 'Dokumente';
    }

    public function getSubheading(): ?string
    {
        return 'Angebote, Rechnungen und Verträge zum Nachlesen und Herunterladen.';
    }
}
