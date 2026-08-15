<?php

namespace App\Filament\Kunde\Resources\Projekte\Tables;

use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjekteTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Projekt')
                    ->weight('medium')
                    ->searchable()
                    ->description(fn (Project $record) => $record->kunden_info
                        ? str($record->kunden_info)->squish()->limit(90)->toString()
                        : null),

                // Die Phase, nicht der interne Status: "pausiert" ist unsere
                // Ablage und für den Kunden keine Auskunft darüber, wie weit
                // seine Seite ist.
                TextColumn::make('phase')
                    ->label('Stand')
                    ->badge(),

                TextColumn::make('offene_anliegen')
                    ->label('Offen')
                    ->badge()
                    ->state(fn (Project $record) => $record->tickets()->offen()->count())
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray'),

                TextColumn::make('am_zug')
                    ->label('Sie sind am Zug')
                    ->badge()
                    ->state(fn (Project $record) => $record->tickets()->wartetAufKunde()->count())
                    // Eine Null wäre hier eine gute Nachricht — aber ein
                    // gelbes Abzeichen liest sich immer wie eine Meldung.
                    // Also bleibt es grau und zeigt einen Strich, solange
                    // nichts zu tun ist.
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '—'),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('ansehen')
                    ->label(fn (Project $record) => $record->zeigtLiveAdresse()
                        ? 'Seite ansehen'
                        : 'Vorschau ansehen')
                    ->icon(fn (Project $record) => $record->zeigtLiveAdresse()
                        ? 'heroicon-o-globe-alt'
                        : 'heroicon-o-eye')
                    ->color('primary')
                    ->url(fn (Project $record) => $record->aktuelleAdresse())
                    ->openUrlInNewTab()
                    ->visible(fn (Project $record) => filled($record->aktuelleAdresse())),

                ViewAction::make()->label('Details'),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-rectangle-group')
            ->emptyStateHeading('Noch keine Projekte freigegeben')
            ->emptyStateDescription('Sobald wir ein Projekt für Sie freischalten, steht es hier — mit Stand, offenen Anliegen und, falls vorhanden, einem Link auf die laufende Fassung.');
    }
}
