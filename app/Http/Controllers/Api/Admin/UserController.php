<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Enums\UserRole;

class UserController extends Controller
{
    /**
     * Create a new staff user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate input and restrict public staff creation to allowed roles only
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:manager,receptionist', // only staff roles
        ]);

        // Map string input to enum explicitly; avoid trusting raw input elsewhere
        $roleMap = [
            'manager' => UserRole::MANAGER,
            'receptionist' => UserRole::RECEPTIONIST,
            'admin' => UserRole::ADMIN,
        ];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // hashed by model cast
            'role' => $roleMap[$validated['role']], // set enum to pass DB checks and casts
            'active' => true, // ensure the account is usable immediately
            'email_verified_at' => now(), // ensure login works under verification gate
        ]);

        return response()->json([
            'message' => 'Staff created successfully',
            'user' => $user, // consistent with existing JSON response style
        ], 201);
    }
}
