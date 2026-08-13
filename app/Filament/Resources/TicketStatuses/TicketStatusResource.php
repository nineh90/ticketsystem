<?php

namespace App\Filament\Resources\TicketStatuses;

use App\Filament\Resources\TicketStatuses\Pages\CreateTicketStatus;
use App\Filament\Resources\TicketStatuses\Pages\EditTicketStatus;
use App\Filament\Resources\TicketStatuses\Pages\ListTicketStatuses;
use App\Filament\Resources\TicketStatuses\Schemas\TicketStatusForm;
use App\Filament\Resources\TicketStatuses\Tables\TicketStatusesTable;
use App\Models\TicketStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TicketStatusResource extends Resource
{
    protected static ?string $model = TicketStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $navigationLabel = 'Ticket-Stadien';

    protected static ?string $modelLabel = 'Stadium';

    protected static ?string $pluralModelLabel = 'Ticket-Stadien';

    protected static string|\UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TicketStatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketStatusesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketStatuses::route('/'),
            'create' => CreateTicketStatus::route('/create'),
            'edit' => EditTicketStatus::route('/{record}/edit'),
        ];
    }
}
