<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            // Kürzel müssen eindeutig sein und passen in 5 Zeichen; ein aus
            // dem Namen abgeleitetes Kürzel kollidiert in Tests zu leicht.
            'kuerzel' => Str::upper(Str::random(4)),
            'aktiv' => true,
        ];
    }

    public function inaktiv(): static
    {
        return $this->state(fn () => ['aktiv' => false]);
    }
}
