<?php

namespace App\Filament\Resources\ReiseplanVorlagen\Pages;

use App\Filament\Resources\ReiseplanVorlagen\ReiseplanVorlageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReiseplanVorlagen extends ListRecords
{
    protected static string $resource = ReiseplanVorlageResource::class;

    public function getTitle(): string
    {
        return 'Reiseplan-Vorlagen';
    }

    public function getSubheading(): ?string
    {
        return 'Die Etappen, die ein Projekt über "Aus Vorlage" mitbekommt. Die Texte stehen wörtlich beim Kunden.';
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Vorlage anlegen'),
        ];
    }
}
