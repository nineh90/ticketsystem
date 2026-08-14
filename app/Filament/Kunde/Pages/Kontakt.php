<?php

namespace App\Filament\Kunde\Pages;

use App\Enums\TicketArt;
use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Kontakt.
 *
 * Die Seite führt bewusst zurück ins System: der auffälligste Knopf legt ein
 * Anliegen an, Telefon und Mail stehen daneben. Andersherum — ein
 * Kontaktformular, das eine Mail verschickt — hätten wir zwei Kanäle, von
 * denen einer keinen Verlauf hat, keinem Projekt zugeordnet ist und beim
 * nächsten Rückruf niemand mehr findet. Genau davon sollte das Ticketsystem
 * wegführen.
 *
 * Telefon und Mail bleiben trotzdem sichtbar. Wer anrufen will, ruft an; die
 * Alternative wäre nicht, dass er ein Anliegen anlegt, sondern dass er sich
 * die Nummer woanders sucht und sich dabei ärgert.
 */
class Kontakt extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?string $navigationLabel = 'Kontakt';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'kontakt';

    protected string $view = 'filament.kunde.pages.kontakt';

    public function getTitle(): string
    {
        return 'Kontakt';
    }

    public function getSubheading(): ?string
    {
        return 'Wir antworten '.config('kontakt.reaktionszeit').'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('anliegen')
                ->label('Anliegen anlegen')
                ->icon('heroicon-o-plus')
                ->url(fn () => AnliegenResource::getUrl('create')),
        ];
    }

    /** Für die Ansicht. */
    public function getKontaktdaten(): array
    {
        return [
            'name' => config('kontakt.name'),
            'email' => config('kontakt.email'),
            'telefon' => config('kontakt.telefon'),
            'website' => config('kontakt.website'),
        ];
    }

    /** Der Weg zum Formular, mit vorgewählter Art. */
    public function frageUrl(): string
    {
        return AnliegenResource::getUrl('create', ['art' => TicketArt::Frage->value]);
    }
}
