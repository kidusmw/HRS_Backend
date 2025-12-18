<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'type' => fake()->randomElement(['Standard', 'Deluxe', 'Suite']),
            'price' => fake()->randomFloat(2, 50, 500),
            'status' => fake()->randomElement([RoomStatus::AVAILABLE, RoomStatus::AVAILABLE, RoomStatus::AVAILABLE, RoomStatus::UNAVAILABLE]), // 75% available
            'capacity' => fake()->numberBetween(1, 6),
            'description' => fake()->sentence(),
        ];
    }
}
