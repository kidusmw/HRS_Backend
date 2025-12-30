<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin if it doesn't exist
        User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'phone_number' => '+251913584756',
                'password' => Hash::make('password'),
                'role' => UserRole::SUPERADMIN,
                'active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Super Admin created: superadmin@example.com / password');
    }
}
