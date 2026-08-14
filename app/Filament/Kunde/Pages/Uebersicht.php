<?php

namespace App\Filament\Kunde\Pages;

use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Die Startseite des Kundenbereichs.
 *
 * Filaments Dashboard, aber mit eigenem Namen und eigener Adresse: "Dashboard"
 * ist ein Wort aus unserer Welt. Wer sich hier anmeldet, kommt auf eine
 * "Übersicht".
 */
class Uebersicht extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Übersicht';

    protected static ?int $navigationSort = 1;

    protected static string $routePath = '/';

    public function getTitle(): string
    {
        return 'Übersicht';
    }

    public function getHeading(): string
    {
        return 'Guten Tag, '.str(auth()->user()->name)->before(' ')->toString();
    }

    public function getSubheading(): ?string
    {
        return auth()->user()->customer?->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('melden')
                ->label('Etwas melden')
                ->icon('heroicon-o-plus')
                ->url(fn () => AnliegenResource::getUrl('create')),
        ];
    }
}
