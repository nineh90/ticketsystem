<?php

namespace App\Filament\Kunde\Widgets;

use App\Filament\Kunde\Resources\Projekte\ProjektResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Die Projekte auf der Übersicht, mit dem Link auf die laufende Fassung
 * direkt daneben.
 *
 * Der Link ist der Grund für dieses Widget. Er ist das, was ein Kunde
 * zwischendurch braucht — "wie sieht es gerade aus?" —, und dafür soll er
 * nicht erst einen Menüpunkt öffnen und eine Zeile anklicken müssen.
 */
class MeineProjekte extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Ihre Projekte')
            ->query(fn (): Builder => Project::query()->sichtbarFuer(auth()->user()))
            ->columns([
                TextColumn::make('name')
                    ->label('Projekt')
                    ->weight('medium')
                    ->description(fn (Project $record) => $record->kunden_info
                        ? str($record->kunden_info)->squish()->limit(90)->toString()
                        : null),

                TextColumn::make('status')
                    ->label('Stand')
                    ->badge(),

                TextColumn::make('offen')
                    ->label('Offene Anliegen')
                    ->state(fn (Project $record) => $record->tickets()->offen()->count())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray'),
            ])
            ->defaultSort('name')
            ->paginated(false)
            ->recordActions([
                Action::make('demo')
                    ->label('Live ansehen')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Project $record) => $record->demo_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Project $record) => filled($record->demo_url)),

                Action::make('details')
                    ->label('Details')
                    ->color('gray')
                    ->url(fn (Project $record) => ProjektResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateIcon('heroicon-o-rectangle-group')
            ->emptyStateHeading('Noch keine Projekte freigegeben')
            ->emptyStateDescription('Sobald wir eines für Sie freischalten, steht es hier.');
    }
}
