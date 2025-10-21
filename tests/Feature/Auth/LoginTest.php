<?php

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function makeUser(string $email, UserRole $role, array $overrides = []): User {
    return User::factory()->create(array_merge([
        'name' => ucfirst($role->name) . ' User',
        'email' => $email,
        'password' => Hash::make('password123'),
        'role' => $role,
        'active' => true,
        'email_verified_at' => now(),
    ], $overrides));
}

it('all_valid_roles_can_login', function () {
    $roles = [UserRole::CLIENT, UserRole::RECEPTIONIST, UserRole::MANAGER, UserRole::ADMIN, UserRole::SUPERADMIN];
    foreach ($roles as $idx => $role) {
        $email = strtolower($role->name) . $idx . '@example.com';
        $user = makeUser($email, $role);

        $res = $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $res->assertOk();
        $res->assertJsonPath('message', 'Login successful');
        $res->assertJsonStructure(['access_token', 'token_type', 'user']);
    }
});

it('inactive_user_cannot_login', function () {
    $user = makeUser('inactive@example.com', UserRole::CLIENT, ['active' => false]);

    $res = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $res->assertForbidden();
    $res->assertJsonPath('message', 'User account is inactive');
});

it('unverified_user_cannot_login', function () {
    $user = makeUser('unverified@example.com', UserRole::CLIENT, ['email_verified_at' => null]);

    $res = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $res->assertForbidden();
    $res->assertJsonPath('message', 'Email not verified');
});


