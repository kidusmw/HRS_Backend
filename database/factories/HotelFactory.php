<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hotel>
 */
class HotelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Hotel',
            'address' => fake()->address(),
            // Generate an E.164-like phone number: +{country}{nsn}
            // e.g. +2519XXXXXXXX
            'phone' => '+2519' . fake()->unique()->numberBetween(10000000, 99999999),
            'email' => fake()->unique()->safeEmail(),
            'description' => fake()->paragraph(),
            'timezone' => fake()->timezone(),
            'logo_path' => null,
        ];
    }
}
