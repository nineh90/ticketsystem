<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 weeks', '-1 hour');
        $minuten = fake()->numberBetween(15, 240);

        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'gestartet_am' => $start,
            'beendet_am' => (clone $start)->modify("+{$minuten} minutes"),
            'minuten' => $minuten,
            'abrechenbar' => true,
        ];
    }

    /** Eine noch laufende Buchung. */
    public function laufend(): static
    {
        return $this->state(fn () => [
            'gestartet_am' => now()->subMinutes(30),
            'beendet_am' => null,
            'minuten' => 0,
        ]);
    }
}
