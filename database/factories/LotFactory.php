<?php

namespace Database\Factories;

use App\Enums\LotStatus;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lot> */
class LotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => PropertyFactory::new(),
            'lot_no' => 'LOT-'.fake()->unique()->numberBetween(1, 9999999),
            'block_no' => null,
            'area_sqm' => '100.00',
            'selling_price' => '600000.00',
            'status' => LotStatus::Available,
        ];
    }
}
