<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Die persönliche Arbeitsliste: was mir zugewiesen und noch offen ist.
 */
class MeineTickets extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Meine offenen Tickets';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Ticket::query()
                ->sichtbarFuer(auth()->user())
                ->offen()
                ->where('assigned_to', auth()->id())
                ->with(['customer', 'project', 'status']))
            // Sortierung nach Dringlichkeit: was überfällig ist, zuerst;
            // danach das mit dem nächsten Termin. Tickets ohne Termin ganz
            // ans Ende, statt sie durch NULL-Sortierung nach vorne zu
            // schwemmen.
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('faellig_am IS NULL')
                ->orderBy('faellig_am')
                ->orderByRaw("CASE prioritaet
                    WHEN 'dringend' THEN 0
                    WHEN 'hoch' THEN 1
                    WHEN 'normal' THEN 2
                    ELSE 3 END"))
            ->columns([
                TextColumn::make('kennung')
                    ->label('Nr.')
                    ->state(fn (Ticket $record) => $record->kennung())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('titel')
                    ->label('Titel')
                    ->wrap()
                    ->weight('medium')
                    ->description(fn (Ticket $record) => $record->customer->name.' · '.$record->project->name),

                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Ticket $record) => Color::hex($record->status->farbe)),

                TextColumn::make('prioritaet')
                    ->label('Priorität')
                    ->badge(),

                TextColumn::make('faellig_am')
                    ->label('Fällig')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->color(fn (Ticket $record) => $record->faellig_am && $record->faellig_am->isPast()
                        ? 'danger'
                        : null),
            ])
            ->recordUrl(fn (Ticket $record) => TicketResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nichts offen')
            ->emptyStateDescription('Dir ist gerade kein offenes Ticket zugewiesen.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
