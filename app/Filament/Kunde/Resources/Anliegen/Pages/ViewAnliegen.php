<?php

namespace App\Filament\Kunde\Resources\Anliegen\Pages;

use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;

class ViewAnliegen extends ViewRecord
{
    protected static string $resource = AnliegenResource::class;

    public function getTitle(): string
    {
        return $this->record->kennung().' — '.$this->record->titel;
    }

    /**
     * Kein Bearbeiten-Knopf, und zwar nicht, weil er hier zufällig fehlt:
     * die Ressource hat gar keine Bearbeiten-Seite (siehe AnliegenResource).
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Der Hinweis ganz oben, wenn wir auf den Kunden warten. Weiter
            // unten stünde er neben fünf anderen Angaben und wäre eine davon;
            // hier ist er das Erste, was er liest.
            Section::make('Wir warten auf Sie')
                ->icon('heroicon-o-hand-raised')
                ->description('Bei diesem Anliegen brauchen wir eine Rückmeldung von Ihnen. Schreiben Sie sie unten unter "Antworten" — wir bekommen sie sofort mit.')
                ->schema([])
                ->visible(fn () => $this->record->wartetAufKunde()),

            Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('status.name')
                        ->label('Stand')
                        ->badge()
                        ->color(fn () => Color::hex($this->record->status?->farbe ?? '#9ca3af')),

                    TextEntry::make('art')
                        ->label('Art')
                        ->badge(),

                    TextEntry::make('project.name')
                        ->label('Projekt'),

                    TextEntry::make('created_at')
                        ->label('Gemeldet am')
                        ->dateTime('d.m.Y'),

                    // Bewusst NICHT dabei: Priorität, Zuständigkeit, Termin
                    // und erfasste Zeit. Das sind unsere Planungsgrößen. Eine
                    // sichtbare Priorität "niedrig" liest sich für den Kunden
                    // wie eine Absage, und ein Termin, den wir intern
                    // verschieben, wäre für ihn ein gebrochenes Versprechen.
                ]),

            Section::make('Bilder')
                ->schema([
                    ViewEntry::make('bilder')
                        ->hiddenLabel()
                        ->view('filament.ticket-bilder'),
                ])
                ->visible(fn () => $this->record->bilder()->exists()),

            Section::make('Beschreibung')
                ->schema([
                    TextEntry::make('beschreibung')
                        ->hiddenLabel()
                        ->placeholder('Keine Beschreibung hinterlegt.')
                        ->prose(),
                ])
                ->collapsible(),
        ]);
    }
}
