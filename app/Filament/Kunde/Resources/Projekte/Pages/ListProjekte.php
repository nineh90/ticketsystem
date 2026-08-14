<?php

namespace App\Filament\Kunde\Resources\Projekte\Pages;

use App\Filament\Kunde\Resources\Projekte\ProjektResource;
use Filament\Resources\Pages\ListRecords;

class ListProjekte extends ListRecords
{
    protected static string $resource = ProjektResource::class;

    public function getTitle(): string
    {
        return 'Ihre Projekte';
    }

    /** Kein Anlegen-Knopf: Projekte entstehen bei uns, nicht hier. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
