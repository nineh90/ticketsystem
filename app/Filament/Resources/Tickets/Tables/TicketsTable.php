<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Enums\Prioritaet;
use App\Support\Sichtbarkeit;
use App\Enums\Quelle;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kennung')
                    ->label('Nr.')
                    ->state(fn ($record) => $record->kennung())
                    ->badge()
                    ->color('gray')
                    // Nach der echten Spalte sortieren, nicht nach dem
                    // zusammengesetzten Text — sonst käme LDX-10 vor LDX-2.
                    ->sortable(['nummer'])
                    ->searchable(['nummer']),

                TextColumn::make('titel')
                    ->label('Titel')
                    ->searchable()
                    ->wrap()
                    ->weight('medium')
                    ->description(fn ($record) => $record->project->name),

                TextColumn::make('customer.name')
                    ->label('Kunde')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => Color::hex($record->status->farbe))
                    ->sortable(),

                TextColumn::make('prioritaet')
                    ->label('Priorität')
                    ->badge()
                    ->sortable(),

                TextColumn::make('zustaendig.name')
                    ->label('Zuständig')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('faellig_am')
                    ->label('Fällig')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn ($record) => $record->faellig_am
                        && $record->faellig_am->isPast()
                        && ! $record->erledigt_at
                            ? 'danger'
                            : null),

                TextColumn::make('quelle')
                    ->label('Herkunft')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Angelegt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('customer')
                    ->label('Kunde')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('project')
                    ->label('Projekt')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('ticket_status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->multiple(),

                SelectFilter::make('prioritaet')
                    ->label('Priorität')
                    ->options(Prioritaet::class)
                    ->multiple(),

                SelectFilter::make('assigned_to')
                    ->label('Zuständig')
                    ->relationship('zustaendig', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('quelle')
                    ->label('Herkunft')
                    ->options(Quelle::class),

                Filter::make('nur_meine')
                    ->label('Nur meine')
                    ->query(fn (Builder $query) => $query->where('assigned_to', auth()->id()))
                    ->toggle(),

                Filter::make('nur_offene')
                    ->label('Nur offene')
                    ->query(fn (Builder $query) => $query->offen())
                    ->toggle()
                    // Standardmäßig an: erledigte Tickets sammeln sich an und
                    // machen die Liste sonst binnen Wochen unbrauchbar.
                    ->default(),

                Filter::make('ueberfaellig')
                    ->label('Überfällig')
                    ->query(fn (Builder $query) => $query
                        ->whereDate('faellig_am', '<', now())
                        ->whereNull('erledigt_at'))
                    ->toggle(),

                Filter::make('unzugewiesen')
                    ->label('Unzugewiesen')
                    ->query(fn (Builder $query) => $query->whereNull('assigned_to'))
                    ->toggle(),
            ])
            // Klick auf die Zeile führt auf die Detailseite mit Kommentaren,
            // Zeiten und Verlauf — nicht direkt ins Bearbeiten-Formular.
            ->recordUrl(fn ($record) => TicketResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->label('Öffnen'),
                EditAction::make()->label('Bearbeiten'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Löschen'),
                ]),
            ])
            ->emptyStateHeading(fn () => Sichtbarkeit::ueberschrift('Keine Tickets'))
            ->emptyStateDescription(fn () => Sichtbarkeit::beschreibung(
                'Entweder gibt es noch keine, oder die Filter sind zu eng gesetzt.',
            ));
    }
}
