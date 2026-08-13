<?php

namespace Database\Factories;

use App\Enums\ProjektStatus;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'customer_id' => Customer::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'status' => ProjektStatus::Aktiv,
        ];
    }
}
