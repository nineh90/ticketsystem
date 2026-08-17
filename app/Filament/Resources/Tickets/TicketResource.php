<?php

namespace App\Filament\Resources\Tickets;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\AktivitaetRelationManager;
use App\Filament\Resources\Tickets\RelationManagers\AnhaengeRelationManager;
use App\Filament\Resources\Tickets\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Resources\Tickets\Schemas\TicketForm;
use App\Filament\Resources\Tickets\Tables\TicketsTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $navigationLabel = 'Tickets';

    protected static ?string $modelLabel = 'Ticket';

    protected static ?string $pluralModelLabel = 'Tickets';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'titel';

    /**
     * Zeigt die Zahl der offenen Tickets am Menüpunkt. Bewusst nur die
     * offenen: die Gesamtzahl wächst monoton und sagt nach einem halben Jahr
     * nichts mehr.
     */
    public static function getNavigationBadge(): ?string
    {
        $anzahl = static::getEloquentQuery()->offen()->count();

        return $anzahl > 0 ? (string) $anzahl : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->sichtbarFuer(auth()->user());
    }

    /**
     * Der Weg zu einer bestimmten Menge Tickets — für alles, was eine Zahl
     * anzeigt und sie beim Wort genommen wissen will: die Dashboard-Kacheln
     * und das "… und N weitere" im Kanban.
     *
     * Die Liste merkt sich Filter, Suche und Reiter über die Sitzung. Für
     * eine Zahl, auf die man klickt, ist das Gift: die Kachel zählt ohne
     * Einschränkung, die Liste zeigte dann noch die Suche von vorhin. Diese
     * Adresse räumt sie deshalb ausdrücklich weg.
     *
     * Drei Teile, und jeder ist nötig:
     *
     *  - `tab` benennt den Reiter (die Schlüssel stehen in ListTickets).
     *  - `filters` steht immer da, auch ohne Zeitfenster: Filament greift auf
     *    die gemerkten Filter nur zurück, wenn die Adresse gar keinen
     *    mitbringt.
     *  - `frisch` löscht die gemerkte Suche. Für sie reicht der leere Wert
     *    nicht — Filament prüft dort auf "leer", nicht auf "nicht gesetzt".
     *
     * @param  array<string, array<string, mixed>>  $weitereFilter
     */
    public static function listeUrl(string $reiter, string $zeitfenster = '', array $weitereFilter = []): string
    {
        return static::getUrl('index', [
            'tab' => $reiter,
            'frisch' => 1,
            'filters' => ['zeitfenster' => ['value' => $zeitfenster]] + $weitereFilter,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return TicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
            AnhaengeRelationManager::class,
            TimeEntriesRelationManager::class,
            AktivitaetRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }
}
