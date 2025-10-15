<?php

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function makeRoleUser(UserRole $role, array $overrides = []): User {
    return User::factory()->create(array_merge([
        'name' => ucfirst($role->name) . ' User',
        'email' => strtolower($role->name) . '@example.com',
        'password' => Hash::make('password123'),
        'role' => $role,
        'active' => true,
        'email_verified_at' => now(),
    ], $overrides));
}

it('admin_can_register_staff_successfully', function () {
    $admin = makeRoleUser(UserRole::ADMIN);
    $this->actingAs($admin, 'sanctum');

    $payload = [
        'name' => 'Receptionist One',
        'email' => 'receptionist1@example.com',
        'password' => 'password123',
        'role' => 'receptionist',
    ];

    $res = $this->postJson('/api/staff', $payload);
    $res->assertStatus(201);
    $this->assertDatabaseHas('users', [
        'email' => 'receptionist1@example.com',
    ]);
});

it('customer_cannot_access_staff_registration_route', function () {
    $customer = makeRoleUser(UserRole::CLIENT);
    $this->actingAs($customer, 'sanctum');

    $payload = [
        'name' => 'Receptionist Two',
        'email' => 'receptionist2@example.com',
        'password' => 'password123',
        'role' => 'receptionist',
    ];

    $res = $this->postJson('/api/staff', $payload);
    $res->assertForbidden();
});

it('staff_cannot_create_other_staff_or_admin', function () {
    $staff = makeRoleUser(UserRole::RECEPTIONIST);
    $this->actingAs($staff, 'sanctum');

    $payload = [
        'name' => 'Receptionist Three',
        'email' => 'receptionist3@example.com',
        'password' => 'password123',
        'role' => 'receptionist',
    ];

    $res = $this->postJson('/api/staff', $payload);
    $res->assertForbidden();
});

it('role_middleware_blocks_customer_from_admin_routes', function () {
    $customer = makeRoleUser(UserRole::CLIENT);
    $this->actingAs($customer, 'sanctum');

    $res = $this->postJson('/api/staff', [
        'name' => 'R', 'email' => 'x@y.com', 'password' => 'password123', 'role' => 'receptionist',
    ]);
    $res->assertForbidden();
});

it('staff_can_login_after_being_registered_by_admin', function () {
    $admin = makeRoleUser(UserRole::ADMIN);
    $this->actingAs($admin, 'sanctum');

    // Admin creates staff
    $this->postJson('/api/staff', [
        'name' => 'Receptionist Four',
        'email' => 'receptionist4@example.com',
        'password' => 'password123',
        'role' => 'receptionist',
    ])->assertStatus(201);

    // Staff can login
    $res = $this->postJson('/api/login', [
        'email' => 'receptionist4@example.com',
        'password' => 'password123',
    ]);
    $res->assertOk()->assertJsonPath('message', 'Login successful');
});

it('tokens_revoked_after_role_change', function () {
    $admin = makeRoleUser(UserRole::ADMIN);
    $this->actingAs($admin, 'sanctum');

    // Create a receptionist
    $this->postJson('/api/staff', [
        'name' => 'Receptionist Five',
        'email' => 'receptionist5@example.com',
        'password' => 'password123',
        'role' => 'receptionist',
    ])->assertStatus(201);

    /** @var User $staff */
    $staff = User::where('email', 'receptionist5@example.com')->first();

    // Staff logs in and gets a token
    $login = $this->postJson('/api/login', [
        'email' => $staff->email,
        'password' => 'password123',
    ])->assertOk();
    $token = $login->json('access_token');

    // Now demote/deactivate the staff (simulate admin action)
    $staff->active = false;
    $staff->save(); // Triggers token revocation via model hook

    // Try to access a protected route with the old token (should fail)
    $res = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/staff', [
            'name' => 'Should Fail',
            'email' => 'fail@example.com',
            'password' => 'password123',
            'role' => 'receptionist',
        ]);
    $res->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated');
});

it('token_abilities_match_role_on_login', function () {
    $admin = makeRoleUser(UserRole::ADMIN);
    $login = $this->postJson('/api/login', [
        'email' => $admin->email,
        'password' => 'password123',
    ])->assertOk();

    // We cannot decode Sanctum token directly; instead validate effect:
    // Admin should be able to hit /api/staff; customer should not.

    $this->withHeader('Authorization', 'Bearer ' . $login->json('access_token'))
        ->postJson('/api/staff', [
            'name' => 'Receptionist Six',
            'email' => 'receptionist6@example.com',
            'password' => 'password123',
            'role' => 'receptionist',
        ])->assertStatus(201);

    $customer = makeRoleUser(UserRole::CLIENT);
    $custLogin = $this->postJson('/api/login', [
        'email' => $customer->email,
        'password' => 'password123',
    ])->assertOk();

    $this->withHeader('Authorization', 'Bearer ' . $custLogin->json('access_token'))
        ->postJson('/api/staff', [
            'name' => 'Should Forbidden',
            'email' => 'forbidden@example.com',
            'password' => 'password123',
            'role' => 'receptionist',
        ])->assertForbidden();
});


