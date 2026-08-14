<?php

namespace App\Filament\Kunde\Resources\Projekte\Pages;

use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use App\Filament\Kunde\Resources\Projekte\ProjektResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewProjekt extends ViewRecord
{
    protected static string $resource = ProjektResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Der Knopf, um den es dem Kunden meistens geht: die laufende
            // Fassung ansehen. Er steht oben rechts und nicht irgendwo im
            // Fließtext — und nur, wenn tatsächlich eine Adresse hinterlegt
            // ist, statt als toter Knopf.
            Action::make('demo')
                ->label('Live ansehen')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => $this->record->demo_url)
                ->openUrlInNewTab()
                ->visible(fn () => filled($this->record->demo_url)),

            Action::make('melden')
                ->label('Etwas melden')
                ->icon('heroicon-o-plus')
                ->color('gray')
                // Projekt vorbelegen: wer von hier aus etwas meldet, meint
                // dieses Projekt und soll es nicht noch einmal auswählen.
                ->url(fn () => AnliegenResource::getUrl('create', [
                    'project_id' => $this->record->getKey(),
                ])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('status')
                        ->label('Stand')
                        ->badge(),

                    TextEntry::make('offen')
                        ->label('Offene Anliegen')
                        ->state(fn () => (string) $this->record->tickets()->offen()->count()),

                    TextEntry::make('am_zug')
                        ->label('Sie sind am Zug')
                        ->state(fn () => (string) $this->record->tickets()->wartetAufKunde()->count())
                        ->color(fn () => $this->record->tickets()->wartetAufKunde()->exists() ? 'warning' : null),

                    TextEntry::make('erledigt')
                        ->label('Erledigt')
                        ->state(fn () => (string) $this->record->tickets()
                            ->whereHas('status', fn ($q) => $q->where('ist_abschluss', true))
                            ->count()),

                    // Bewusst nicht dabei: Budget-Stunden und erfasste Zeit.
                    // Das ist unsere Kalkulation. Eine Zahl wie "38 von 40
                    // Stunden verbraucht" beantwortet keine Frage des Kunden,
                    // löst aber verlässlich eine aus.
                ]),

            Section::make('Zum Stand')
                ->schema([
                    TextEntry::make('kunden_info')
                        ->hiddenLabel()
                        ->prose(),
                ])
                ->visible(fn () => filled($this->record->kunden_info)),

            Section::make('Live-Fassung')
                ->description('Hier läuft der aktuelle Stand. Was Sie dort sehen, ist der Stand von jetzt — nicht der letzte Abgabestand.')
                ->schema([
                    TextEntry::make('demo_url')
                        ->hiddenLabel()
                        ->url(fn () => $this->record->demo_url)
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-globe-alt')
                        ->color('primary'),
                ])
                ->visible(fn () => filled($this->record->demo_url)),
        ]);
    }
}
