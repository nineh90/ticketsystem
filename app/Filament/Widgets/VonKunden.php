<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Was von außen gemeldet wurde und noch offen ist — von Kunden im
 * Kundenbereich und, seit NID-24, von Anfragenden über die Website.
 *
 * Beides in einer Liste, weil es für uns dieselbe Lage ist: draußen wartet
 * jemand. Eine Anfrage von jemandem, der noch kein Kunde ist, gehört eher
 * weiter nach oben als nach unten.
 *
 * Das Widget steht ganz oben auf dem Dashboard und verschwindet vollständig,
 * sobald nichts offen ist. Beides gehört zusammen: eine Liste, die meistens
 * leer dasteht, lernt man zu überblättern — und dann ist sie an dem Tag
 * unsichtbar, an dem etwas darin steht.
 *
 * Gegenüber der Glocke ist es die zweite, ruhigere Ebene: die Glocke meldet
 * das Ereignis einmal, diese Liste zeigt, was davon noch offen ist. Eine
 * weggeklickte Benachrichtigung darf ein unbeantwortetes Kundenanliegen nicht
 * aus der Welt schaffen.
 */
class VonKunden extends TableWidget
{
    /**
     * Vor allem anderen. Wenn ein Kunde wartet, ist das die erste
     * Information des Tages — vor den eigenen Zahlen.
     */
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Von außen gemeldet';
    }

    /**
     * Nur zeigen, wenn es etwas zu zeigen gibt.
     *
     * canView() entscheidet für alle gleich; die Abfrage darunter ist
     * trotzdem auf den jeweiligen Nutzer beschränkt. Beides ist nötig: ein
     * Mitarbeiter, für dessen Projekte nichts vorliegt, bekäme sonst eine
     * leere Karte an der prominentesten Stelle des Dashboards.
     */
    public static function canView(): bool
    {
        $nutzer = auth()->user();

        if ($nutzer === null) {
            return false;
        }

        return Ticket::query()
            ->sichtbarFuer($nutzer)
            ->offen()
            ->vonAussen()
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Ticket::query()
                ->sichtbarFuer(auth()->user())
                ->offen()
                ->vonAussen()
                ->with(['customer', 'project', 'status', 'ersteller']))
            // Ältestes zuerst — nicht neuestes. Das Anliegen, das am
            // längsten unbeantwortet liegt, ist das dringendste, auch wenn
            // gerade ein neues hereingekommen ist.
            ->defaultSort('created_at', 'asc')
            ->columns([
                // Das Logo als erste Spalte: auf dem Dashboard erkennt man
                // damit auf einen Blick, von welchem Kunden etwas liegt,
                // bevor man eine Zeile gelesen hat.
                ImageColumn::make('customer.logo')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->visibility('public'),

                TextColumn::make('kennung')
                    ->label('Nr.')
                    ->state(fn (Ticket $record) => $record->kennung())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('art')
                    ->label('Art')
                    ->badge(),

                TextColumn::make('titel')
                    ->label('Anliegen')
                    ->wrap()
                    ->weight('medium')
                    ->description(fn (Ticket $record) => $record->istVonDerWebsite()
                        ? 'Über die Website'
                        : $record->customer->name.' · '.$record->project->name),

                // Bei einer Anfrage über die Website gibt es kein Konto,
                // nur den Absender, den sie mitgeschickt hat.
                TextColumn::make('ersteller.name')
                    ->label('Von')
                    ->state(fn (Ticket $record) => $record->ersteller?->name
                        ?? $record->absender_name
                        ?? $record->absender_email)
                    ->placeholder('—'),

                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Ticket $record) => Color::hex($record->status->farbe)),

                TextColumn::make('created_at')
                    ->label('Wartet seit')
                    ->since()
                    ->sortable()
                    // Die Farbe macht aus einer Angabe eine Aufforderung:
                    // ab drei Tagen ohne Reaktion ist es keine Kleinigkeit
                    // mehr, sondern etwas, das der Kunde bemerkt hat.
                    ->color(fn (Ticket $record) => $record->created_at?->lt(now()->subDays(3))
                        ? 'danger'
                        : null),
            ])
            ->recordUrl(fn (Ticket $record) => TicketResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
