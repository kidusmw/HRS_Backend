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
    $this->hotel = Hotel::factory()->create();
});

it('lists users with pagination', function () {
    User::factory()->count(20)->create();

    $response = test()->actingAs($this->superAdmin)
        ->getJson('/api/super_admin/users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'role', 'isActive'],
            ],
            'links',
            'meta',
        ]);
});

it('filters users by role', function () {
    User::factory()->count(3)->create(['role' => UserRole::ADMIN]);
    User::factory()->count(2)->create(['role' => UserRole::CLIENT]);

    $response = test()->actingAs($this->superAdmin)
        ->getJson('/api/super_admin/users?role=admin');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeArray();
    expect($response->json('data'))->not->toBeEmpty();
});

it('filters users by hotel', function () {
    $hotel = Hotel::factory()->create();
    User::factory()->count(2)->create(['hotel_id' => $hotel->id]);
    User::factory()->count(3)->create(['hotel_id' => null]);

    $response = test()->actingAs($this->superAdmin)
        ->getJson("/api/super_admin/users?hotelId={$hotel->id}");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('creates a new user', function () {
    $userData = [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'password' => 'password123',
        'role' => 'client',
        'active' => true,
    ];

    $response = test()->actingAs($this->superAdmin)
        ->postJson('/api/super_admin/users', $userData);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role']]);

    expect(User::where('email', 'testuser@example.com')->exists())->toBeTrue();
});

it('creates user with auto-generated password when generatePassword is true', function () {
    $userData = [
        'name' => 'Auto Password User',
        'email' => 'autopass@example.com',
        'role' => 'receptionist',
        'generatePassword' => true,
    ];

    $response = test()->actingAs($this->superAdmin)
        ->postJson('/api/super_admin/users', $userData);

    $response->assertStatus(201);
    
    $user = User::where('email', 'autopass@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->password)->not->toBeNull();
});

it('requires password when generatePassword is false or not provided', function () {
    $userData = [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'role' => 'client',
        'generatePassword' => false,
    ];

    $response = test()->actingAs($this->superAdmin)
        ->postJson('/api/super_admin/users', $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('updates a user', function () {
    $user = User::factory()->create();

    $response = test()->actingAs($this->superAdmin)
        ->putJson("/api/super_admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Name');

    expect(User::find($user->id)->name)->toBe('Updated Name');
});

it('activates a user', function () {
    $user = User::factory()->create(['active' => false]);

    $response = test()->actingAs($this->superAdmin)
        ->patchJson("/api/super_admin/users/{$user->id}/activate");

    $response->assertStatus(200);
    
    expect($user->fresh()->active)->toBeTrue();
});

it('deactivates a user', function () {
    $user = User::factory()->create(['active' => true]);

    $response = test()->actingAs($this->superAdmin)
        ->patchJson("/api/super_admin/users/{$user->id}/deactivate");

    $response->assertStatus(200);
    
    expect($user->fresh()->active)->toBeFalse();
});

it('resets a user password', function () {
    $user = User::factory()->create();

    $response = test()->actingAs($this->superAdmin)
        ->postJson("/api/super_admin/users/{$user->id}/reset-password");

    $response->assertStatus(200)
        ->assertJsonStructure(['message', 'password']);
    
    expect($response->json('password'))->toBeString()->not->toBeEmpty();
});

it('requires authentication to access users', function () {
    $response = test()->getJson('/api/super_admin/users');
    $response->assertStatus(401);
});

it('requires super admin role to create users', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
        'active' => true,
        'email_verified_at' => now(),
    ]);

    $response = test()->actingAs($admin)
        ->postJson('/api/super_admin/users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'role' => 'client',
        ]);

    $response->assertStatus(403);
});
