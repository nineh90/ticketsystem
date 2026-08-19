<?php

namespace App\Filament\Resources\Treffen\Pages;

use App\Filament\Resources\Treffen\TreffenResource;
use App\Models\Treffen;
use App\Support\Messe;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Arr;

/**
 * Die Messe — alle Treffen auf einen Blick.
 *
 * Anlegen läuft über ein Fenster und nicht über eine eigene Seite: ein
 * Termin sind sieben Felder, und wer ihn ansetzt, will danach die Liste
 * sehen und nicht erst zurücknavigieren.
 */
class ListTreffen extends ListRecords
{
    protected static string $resource = TreffenResource::class;

    public function getTitle(): string
    {
        return 'Messe';
    }

    public function getSubheading(): ?string
    {
        return 'Treffen mit Kunden — und alles, was nur uns betrifft.';
    }

    /**
     * Kein Brotkrumen: die Seite hat keine Ebene über sich.
     *
     * Filament baut ihn aus dem Modellnamen und käme auf "Treffen ›
     * Übersicht" — über einer Überschrift, die "Messe" heißt, und mit einem
     * Link auf genau die Seite, auf der man schon steht.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Treffen ansetzen')
                ->modalHeading('Treffen ansetzen')
                ->mutateDataUsing(function (array $data): array {
                    $data['erstellt_von'] = auth()->id();

                    return $data;
                })
                // Die Crew wird nach dem Anlegen gesetzt und nicht über
                // Filaments ->relationship(): nur so weiß Messe, WER neu
                // dazugekommen ist, und meldet sich nur bei denen.
                ->using(function (array $data): Treffen {
                    $crew = Arr::pull($data, 'crew_ids', []);

                    $treffen = Treffen::create($data);

                    Messe::crewSetzen($treffen, $crew);

                    return $treffen;
                }),
        ];
    }
}
