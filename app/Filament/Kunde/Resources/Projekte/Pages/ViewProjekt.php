<?php

namespace App\Filament\Kunde\Resources\Projekte\Pages;

use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use App\Filament\Kunde\Resources\Projekte\ProjektResource;
use App\Models\Zugangsdaten;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class ViewProjekt extends ViewRecord
{
    protected static string $resource = ProjektResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return $this->record->phase->getDescription();
    }

    protected function getHeaderActions(): array
    {
        $adresse = $this->record->aktuelleAdresse();
        $istLive = $this->record->zeigtLiveAdresse();

        return [
            // Der Knopf, um den es dem Kunden meistens geht. Welche der
            // beiden Adressen dahinterliegt, entscheidet die Phase: vor der
            // Veröffentlichung die Vorschau, danach die eigene Adresse.
            // Beschriftung und Symbol richten sich nach der Adresse, die
            // dabei herauskommt — sonst steht "Vorschau ansehen" auf einem
            // Knopf, der auf die fertige Seite führt.
            Action::make('ansehen')
                ->label($istLive ? 'Seite ansehen' : 'Vorschau ansehen')
                ->icon($istLive ? 'heroicon-o-globe-alt' : 'heroicon-o-eye')
                ->url($adresse)
                ->openUrlInNewTab()
                ->visible(filled($adresse)),

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
                    TextEntry::make('phase')
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

            Section::make('Woran wir gerade arbeiten')
                ->schema([
                    TextEntry::make('kunden_info')
                        ->hiddenLabel()
                        ->prose(),
                ])
                ->visible(fn () => filled($this->record->kunden_info)),

            // "Reiseplan" und nicht "Meilensteine": das Wort steht beim
            // Passagier, und ein Passagier bekommt einen Reiseplan. Intern
            // heißt der Reiter weiter Meilensteine — dort planen wir, hier
            // liest jemand, wohin die Fahrt geht.
            Section::make('Ihr Reiseplan')
                ->description('Die Etappen bis zur fertigen Fassung.')
                ->schema([
                    View::make('filament.kunde.meilensteine')
                        ->viewData(fn () => [
                            'meilensteine' => $this->record->meilensteine()
                                ->kundenSichtbar()
                                ->inReihenfolge()
                                ->get(),
                            'anteil' => $this->record->fortschritt(),
                        ]),
                ])
                ->visible(fn () => $this->record->meilensteine()->kundenSichtbar()->exists()),

            Section::make('Ihre Zugangsdaten')
                ->description('Damit kommen Sie selbst hinein. Das Passwort wird erst auf Klick sichtbar.')
                ->icon('heroicon-o-key')
                ->schema([
                    View::make('filament.kunde.zugangsdaten')
                        ->viewData(fn () => [
                            'eintraege' => $this->zugangsdaten(),
                        ]),
                ])
                ->visible(fn () => $this->zugangsdaten()->isNotEmpty()),

            Section::make('Adressen')
                ->columns(2)
                ->schema([
                    TextEntry::make('live_url')
                        ->label('Ihre Seite')
                        ->url(fn () => $this->record->live_url)
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-globe-alt')
                        ->color('primary')
                        ->visible(fn () => filled($this->record->live_url)),

                    TextEntry::make('demo_url')
                        ->label('Vorschau')
                        ->helperText('Hier läuft der aktuelle Zwischenstand — nicht der letzte Abgabestand.')
                        ->url(fn () => $this->record->demo_url)
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-eye')
                        ->color('primary')
                        ->visible(fn () => filled($this->record->demo_url)),
                ])
                ->visible(fn () => filled($this->record->live_url) || filled($this->record->demo_url)),
        ]);
    }

    /**
     * Die Zugangsdaten zu diesem Projekt.
     *
     * Über sichtbarFuer, obwohl hier ohnehin nur das eigene Projekt in Frage
     * kommt: der Scope ist die einzige Stelle, an der "kunden_sichtbar"
     * geprüft wird, und ohne ihn stünden hier auch unsere Serverzugänge.
     *
     * @return Collection<int, Zugangsdaten>
     */
    protected function zugangsdaten()
    {
        return Zugangsdaten::query()
            ->sichtbarFuer(auth()->user())
            ->where('project_id', $this->record->getKey())
            ->inReihenfolge()
            ->get();
    }
}
