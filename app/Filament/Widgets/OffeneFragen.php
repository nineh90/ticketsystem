<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Comment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fragen an mich, auf die noch niemand geantwortet hat (NID-36).
 *
 * Die Glocke meldet eine Frage einmal; diese Liste zeigt, was davon noch
 * offen ist — dieselbe Arbeitsteilung wie bei VonKunden. Eine weggeklickte
 * Meldung darf eine Frage nicht aus der Welt schaffen, auf die ein Kollege
 * mit seiner Arbeit wartet.
 *
 * Erscheint nur, wenn etwas offen ist: eine Karte, die meistens leer
 * dasteht, überblättert man an dem Tag, an dem etwas darin steht.
 */
class OffeneFragen extends TableWidget
{
    /** Gleich nach dem, was von außen kommt: auch hier wartet jemand. */
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Fragen an dich';
    }

    public static function canView(): bool
    {
        $nutzer = auth()->user();

        return $nutzer !== null
            && ! $nutzer->istKunde()
            && self::abfrage()->exists();
    }

    private static function abfrage(): Builder
    {
        return Comment::query()
            ->offeneFragen()
            ->where('frage_an', auth()->id())
            // Nur an Tickets, die er noch sehen darf — und die es noch gibt.
            ->whereHas('ticket', fn (Builder $q) => $q->sichtbarFuer(auth()->user()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::abfrage()->with(['ticket.customer', 'autor']))
            // Die älteste Frage zuerst — sie wartet am längsten.
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('ticket.nummer')
                    ->label('Ticket')
                    ->state(fn (Comment $record) => $record->ticket->kennung())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('body')
                    ->label('Frage')
                    ->wrap()
                    ->limit(240)
                    ->description(fn (Comment $record) => $record->ticket->titel.' · '.$record->ticket->customer?->name),

                TextColumn::make('autor.name')
                    ->label('Von')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Wartet seit')
                    ->since()
                    ->color(fn (Comment $record) => $record->created_at->lt(now()->subDays(2)) ? 'danger' : null),
            ])
            ->recordUrl(fn (Comment $record) => TicketResource::getUrl('view', ['record' => $record->ticket]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
