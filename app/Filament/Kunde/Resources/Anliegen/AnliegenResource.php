<?php

namespace App\Filament\Kunde\Resources\Anliegen;

use App\Filament\Kunde\Resources\Anliegen\Pages\CreateAnliegen;
use App\Filament\Kunde\Resources\Anliegen\Pages\ListAnliegen;
use App\Filament\Kunde\Resources\Anliegen\Pages\ViewAnliegen;
use App\Filament\Kunde\Resources\Anliegen\RelationManagers\AntwortenRelationManager;
use App\Filament\Kunde\Resources\Anliegen\RelationManagers\DateienRelationManager;
use App\Filament\Kunde\Resources\Anliegen\Schemas\AnliegenForm;
use App\Filament\Kunde\Resources\Anliegen\Tables\AnliegenTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Anliegen — dasselbe Model wie intern die Tickets, nur anders benannt und
 * radikal beschnitten.
 *
 * "Ticket" ist unser Wort. Wer einen Fehler an seiner Website meldet, hat
 * keinen Ticket, sondern ein Anliegen; das Wort steht deshalb überall im
 * Kundenbereich statt der internen Bezeichnung.
 *
 * Beschnitten heißt: es gibt keine Bearbeiten-Seite. Ein Kunde legt an,
 * liest und antwortet — Status, Priorität, Zuständigkeit und Termin sind
 * unsere Entscheidungen und stehen deshalb nirgends in einem Formular, das
 * er ausfüllen kann. Nachträglich am eigenen Anliegen herumzuschreiben ist
 * ebenfalls nicht vorgesehen: was besprochen wurde, gehört in die Antworten,
 * wo es mit Zeitstempel und Urheber stehen bleibt.
 */
class AnliegenResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Anliegen';

    protected static ?string $modelLabel = 'Anliegen';

    protected static ?string $pluralModelLabel = 'Anliegen';

    protected static ?int $navigationSort = 20;

    // Ohne diese Zeile leitet Filament den Pfad aus Ordner und Modellnamen ab
    // und macht daraus /kunde/anliegen/anliegens. Adressen sieht der Kunde,
    // und sie sind das Erste, was unfertig wirkt.
    protected static ?string $slug = 'anliegen';

    protected static ?string $recordTitleAttribute = 'titel';

    /**
     * Dieselbe Regel wie überall: sichtbarFuer kennt die Rolle "kunde" und
     * liefert dann die Tickets der freigegebenen Projekte seines Kunden.
     * Die Zeile ist die einzige Stelle, an der dieser Bereich entscheidet,
     * was existiert.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->sichtbarFuer(auth()->user());
    }

    /**
     * Die Zahl am Menüpunkt zeigt, worauf gewartet wird — nicht, wie viel
     * offen ist. Offene Anliegen sind unsere Arbeitslast, aber "Sie sind am
     * Zug" ist die einzige Zahl, auf die der Kunde selbst handeln kann.
     */
    public static function getNavigationBadge(): ?string
    {
        $anzahl = static::getEloquentQuery()->wartetAufKunde()->count();

        return $anzahl > 0 ? (string) $anzahl : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return AnliegenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnliegenTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AntwortenRelationManager::class,
            DateienRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnliegen::route('/'),
            'create' => CreateAnliegen::route('/neu'),
            'view' => ViewAnliegen::route('/{record}'),
        ];
    }
}
