<?php

use App\Models\User;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Reservation;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superAdmin = User::factory()->create([
        'role' => UserRole::SUPERADMIN,
        'active' => true,
        'email_verified_at' => now(),
    ]);
});

it('returns dashboard metrics for super admin', function () {
    Hotel::factory()->count(3)->create();
    User::factory()->count(5)->create(['role' => UserRole::CLIENT]);
    $hotel = Hotel::first();
    Room::factory()->count(10)->create(['hotel_id' => $hotel->id]);
    Reservation::factory()->count(3)->create([
        'room_id' => Room::first()->id,
        'status' => 'confirmed',
    ]);

    $response = test()->actingAs($this->superAdmin)
        ->getJson('/api/super_admin/dashboard/metrics');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'hotels',
            'usersByRole' => [
                'client',
                'receptionist',
                'manager',
                'admin',
                'super_admin',
            ],
            'totalBookings',
            'rooms' => [
                'available',
                'occupied',
            ],
        ]);

    expect($response->json('hotels'))->toBe(3);
    expect($response->json('totalBookings'))->toBeGreaterThanOrEqual(0);
});

it('requires authentication', function () {
    $response = test()->getJson('/api/super_admin/dashboard/metrics');
    $response->assertStatus(401);
});

it('requires super admin role', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
        'active' => true,
        'email_verified_at' => now(),
    ]);

    $response = test()->actingAs($admin)
        ->getJson('/api/super_admin/dashboard/metrics');

    $response->assertStatus(403);
});
