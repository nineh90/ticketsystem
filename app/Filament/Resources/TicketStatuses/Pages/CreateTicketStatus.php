<?php

namespace App\Filament\Resources\TicketStatuses\Pages;

use App\Filament\Resources\TicketStatuses\TicketStatusResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketStatus extends CreateRecord
{
    protected static string $resource = TicketStatusResource::class;
}
