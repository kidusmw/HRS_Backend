<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\UserRole;
uses(RefreshDatabase::class);

function register(array $overrides = []): \Illuminate\Testing\TestResponse {
    $payload = array_merge([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);

    return test()->postJson('/api/register', $payload);
}

it('customer_can_register_but_staff_cannot', function () {
    // Customer can register publicly
    $res = register();
    $res->assertCreated();
    $res->assertJsonPath('message', 'User registered successfully');
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
    ]);

    // Attempt to override role should be ignored, user must still be customer
    $res2 = register([
        'email' => 'elevate@example.com',
        'role' => 'admin', // should be ignored by controller
    ]);
    $res2->assertCreated();
    $user = User::where('email', 'elevate@example.com')->first();
    expect($user)->not()->toBeNull();
    // Enum will be serialized server-side, but we assert DB role remains client
    expect((string) $user->role->name)->toBe(UserRole::CLIENT->name);
});


