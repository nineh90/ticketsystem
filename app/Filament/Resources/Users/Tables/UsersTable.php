<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Rolle;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('E-Mail kopiert'),

                TextColumn::make('rolle')
                    ->label('Rolle')
                    ->badge()
                    ->sortable(),

                IconColumn::make('panel_zugang')
                    ->label('Freigegeben')
                    ->boolean(),

                IconColumn::make('aktiv')
                    ->label('Aktiv')
                    ->boolean(),

                TextColumn::make('projects_count')
                    ->label('Projekte')
                    ->counts('projects')
                    ->alignEnd()
                    // Für Admins ohne Aussage — sie brauchen keine Zuordnung.
                    ->tooltip('Nur für Mitarbeiter relevant'),

                TextColumn::make('created_at')
                    ->label('Angelegt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('rolle')
                    ->label('Rolle')
                    ->options(Rolle::class),

                TernaryFilter::make('panel_zugang')
                    ->label('Zugang freigegeben'),

                TernaryFilter::make('aktiv')
                    ->label('Aktiv')
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Löschen'),
                ]),
            ])
            ->emptyStateHeading('Noch keine Nutzer')
            ->emptyStateDescription('Lege Mitarbeiter an und gib ihnen den Zugang frei.');
    }
}
