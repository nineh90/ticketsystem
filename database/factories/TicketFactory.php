<?php

namespace Database\Factories;

use App\Enums\Prioritaet;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'titel' => ucfirst(fake()->words(4, true)),
            'beschreibung' => fake()->paragraph(),
            // Vorhandenes Stadium wiederverwenden, sonst legt jedes Ticket in
            // einem Test eine eigene Spalte an und das Kanban wird unlesbar.
            'ticket_status_id' => TicketStatus::query()->sortiert()->value('id')
                ?? TicketStatus::factory(),
            'prioritaet' => Prioritaet::Normal,
        ];
    }
}
