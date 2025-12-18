<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Enums\RoomStatus;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Reservation;
use App\Models\User;
use App\Enums\UserRole;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample hotels
        $hotel1 = Hotel::create([
            'name' => 'Grand Hotel',
            'address' => '123 Main Street, City Center',
            'phone' => '+1234567890',
            'email' => 'info@grandhotel.com',
            'description' => 'A luxurious hotel in the heart of the city',
            'timezone' => 'America/New_York',
        ]);

        $hotel2 = Hotel::create([
            'name' => 'Plaza Hotel',
            'address' => '456 Park Avenue',
            'phone' => '+1234567891',
            'email' => 'info@plazahotel.com',
            'description' => 'Modern hotel with excellent amenities',
            'timezone' => 'America/Los_Angeles',
        ]);

        $hotel3 = Hotel::create([
            'name' => 'Ocean View Hotel',
            'address' => '789 Beach Road',
            'phone' => '+1234567892',
            'email' => 'info@oceanview.com',
            'description' => 'Beachfront hotel with stunning ocean views',
            'timezone' => 'America/Chicago',
        ]);

        // Create admin users for hotels
        $admin1 = User::create([
            'name' => 'Hotel Admin 1',
            'email' => 'admin1@grandhotel.com',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN,
            'hotel_id' => $hotel1->id,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $admin2 = User::create([
            'name' => 'Hotel Admin 2',
            'email' => 'admin2@plazahotel.com',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN,
            'hotel_id' => $hotel2->id,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        // Create sample rooms
        foreach ([$hotel1, $hotel2, $hotel3] as $hotel) {
            for ($i = 1; $i <= 10; $i++) {
                Room::create([
                    'hotel_id' => $hotel->id,
                    'type' => $i <= 3 ? 'Standard' : ($i <= 7 ? 'Deluxe' : 'Suite'),
                    'price' => $i <= 3 ? 100.00 : ($i <= 7 ? 150.00 : 250.00),
                    'status' => ($i % 3 !== 0) ? RoomStatus::AVAILABLE : RoomStatus::UNAVAILABLE,
                    'capacity' => $i <= 7 ? 2 : 4,
                    'description' => "Comfortable {$hotel->name} room",
                ]);
            }
        }

        // Create sample reservations
        $client1 = User::firstOrCreate(
            ['email' => 'client1@example.com'],
            [
                'name' => 'John Client',
                'email' => 'client1@example.com',
                'password' => bcrypt('password'),
                'role' => UserRole::CLIENT,
                'active' => true,
                'email_verified_at' => now(),
            ]
        );

        $room1 = Room::where('hotel_id', $hotel1->id)->first();
        if ($room1) {
            Reservation::create([
                'room_id' => $room1->id,
                'user_id' => $client1->id,
                'check_in' => now()->addDays(7),
                'check_out' => now()->addDays(10),
                'status' => 'confirmed',
                'guests' => 2,
                'special_requests' => 'Late checkout preferred',
            ]);
        }

        $this->command->info('Sample data created: 3 hotels, 2 admin users, 30 rooms, 1 reservation');
    }
}
