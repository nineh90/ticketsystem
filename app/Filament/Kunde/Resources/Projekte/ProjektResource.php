<?php

namespace App\Filament\Kunde\Resources\Projekte;

use App\Filament\Kunde\Resources\Projekte\Pages\ListProjekte;
use App\Filament\Kunde\Resources\Projekte\Pages\ViewProjekt;
use App\Filament\Kunde\Resources\Projekte\RelationManagers\AnliegenRelationManager;
use App\Filament\Kunde\Resources\Projekte\Tables\ProjekteTable;
use App\Models\Project;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Die Projekte des Kunden — nur ansehen, nie ändern.
 *
 * Die Ressource hat weder Formular noch Anlegen- oder Bearbeiten-Seite. Das
 * ist keine Bequemlichkeit, sondern die Absicherung: was es nicht gibt, kann
 * auch nicht über eine nachgebaute Anfrage aufgerufen werden.
 */
class ProjektResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $navigationLabel = 'Projekte';

    protected static ?string $modelLabel = 'Projekt';

    protected static ?string $pluralModelLabel = 'Projekte';

    protected static ?int $navigationSort = 10;

    /** Sonst wird daraus /kunde/projekte/projekts. */
    protected static ?string $slug = 'projekte';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * sichtbarFuer kennt die Rolle "kunde" und liefert dann ausschließlich
     * die freigegebenen Projekte seines Kunden (kunden_sichtbar).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->sichtbarFuer(auth()->user());
    }

    public static function table(Table $table): Table
    {
        return ProjekteTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AnliegenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjekte::route('/'),
            'view' => ViewProjekt::route('/{record}'),
        ];
    }
}
