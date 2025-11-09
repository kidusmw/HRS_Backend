<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('now', '+30 days');
        $checkOut = fake()->dateTimeBetween($checkIn, '+7 days');

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => fake()->randomElement(['pending', 'confirmed', 'cancelled', 'completed']),
            'guests' => fake()->numberBetween(1, 4),
            'special_requests' => fake()->optional()->sentence(),
        ];
    }
}
