<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'code' => fake()->unique()->lexify('????'),
            'description' => fake()->sentence(),
            'max_days_per_year' => fake()->numberBetween(10, 30),
            'requires_attachment' => fake()->boolean(),
            'is_active' => true,
        ];
    }
}