<?php

namespace Database\Factories;

use App\Enums\PropertyLocation;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'location' => fake()->randomElement(PropertyLocation::cases())->value,
            'address' => fake()->address(),
            'total_area_sqm' => '10000.00',
            'description' => 'Property information used only in automated tests.',
        ];
    }
}
