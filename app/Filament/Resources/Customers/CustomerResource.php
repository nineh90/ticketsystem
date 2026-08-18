<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\DokumenteRelationManager;
use App\Filament\Resources\Customers\RelationManagers\KontakteRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ProjectsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ZugaengeRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ZugangsdatenRelationManager;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Kunden';

    protected static ?string $modelLabel = 'Kunde';

    protected static ?string $pluralModelLabel = 'Kunden';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Mitarbeiter sehen nur Kunden, bei denen ihnen ein Projekt zugeordnet
     * ist. Das hier ist die Ergänzung zur CustomerPolicy: die Policy wehrt
     * den Direktaufruf ab, dieser Scope hält fremde Kunden aus Liste, Suche
     * und Zählern heraus.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->sichtbarFuer(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProjectsRelationManager::class,
            // Reihenfolge nach Alltag: erst wer beteiligt ist, dann womit man
            // reinkommt, zuletzt die Anmeldekonten — die legt man einmal an
            // und sieht sie danach selten wieder. Die Dokumente stehen weit
            // vorn, weil sie das Einzige sind, das laufend dazukommt.
            DokumenteRelationManager::class,
            KontakteRelationManager::class,
            ZugangsdatenRelationManager::class,
            ZugaengeRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            // Die Akte zum Ansehen ist der Ort, an dem man landet; das
            // Formular liegt einen Knopf weiter. Vorher gab es nur das
            // Formular, und damit keine Stelle, an der Zahlen zu einem
            // Kunden stehen konnten.
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
