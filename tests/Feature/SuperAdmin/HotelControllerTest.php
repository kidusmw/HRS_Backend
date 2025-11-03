<?php

use App\Models\User;
use App\Models\Hotel;
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

it('lists hotels with pagination', function () {
    Hotel::factory()->count(15)->create();

    $response = test()->actingAs($this->superAdmin)
        ->getJson('/api/super_admin/hotels');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'address', 'timezone'],
            ],
            'links',
            'meta',
        ]);
});

it('filters hotels by search term', function () {
    Hotel::factory()->create(['name' => 'Grand Hotel']);
    Hotel::factory()->create(['name' => 'Plaza Hotel']);
    Hotel::factory()->create(['name' => 'Ocean View']);

    $response = test()->actingAs($this->superAdmin)
        ->getJson('/api/super_admin/hotels?search=Grand');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toContain('Grand');
});

it('creates a hotel', function () {
    $hotelData = [
        'name' => 'Test Hotel',
        'address' => '123 Test Street',
        'phone' => '+1234567890',
        'email' => 'test@hotel.com',
        'timezone' => 'America/New_York',
        'description' => 'Test description',
    ];

    $response = test()->actingAs($this->superAdmin)
        ->postJson('/api/super_admin/hotels', $hotelData);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Test Hotel');

    expect(Hotel::where('name', 'Test Hotel')->exists())->toBeTrue();
});

it('updates a hotel', function () {
    $hotel = Hotel::factory()->create();

    $response = test()->actingAs($this->superAdmin)
        ->putJson("/api/super_admin/hotels/{$hotel->id}", [
            'name' => 'Updated Hotel Name',
            'timezone' => 'America/Los_Angeles',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Hotel Name');

    expect($hotel->fresh()->name)->toBe('Updated Hotel Name');
});

it('deletes a hotel', function () {
    $hotel = Hotel::factory()->create();

    $response = test()->actingAs($this->superAdmin)
        ->deleteJson("/api/super_admin/hotels/{$hotel->id}");

    $response->assertStatus(200);
    
    expect(Hotel::find($hotel->id))->toBeNull();
});

it('requires authentication to access hotels', function () {
    $response = test()->getJson('/api/super_admin/hotels');
    $response->assertStatus(401);
});

it('requires super admin role to create hotels', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
        'active' => true,
        'email_verified_at' => now(),
    ]);

    $response = test()->actingAs($admin)
        ->postJson('/api/super_admin/hotels', [
            'name' => 'Test Hotel',
            'timezone' => 'UTC',
        ]);

    $response->assertStatus(403);
});
