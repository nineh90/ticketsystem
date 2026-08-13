<?php

namespace Database\Factories;

use App\Models\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketStatus>
 */
class TicketStatusFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'sortierung' => fake()->numberBetween(1, 100),
            'ist_abschluss' => false,
        ];
    }

    public function abschluss(): static
    {
        return $this->state(fn () => ['ist_abschluss' => true]);
    }
}
