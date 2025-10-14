<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function store(Request $request)
    {
        // Logic to create a new staff user (admin, manager, receptionist)
        //TODO: Implement Hotel association logic
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,manager,receptionist',
        ]);

        $user = User::create($validated);

        return response()->json([
            'message' => 'Staff user created successfully',
            'user' => $user,
        ], 201);
    }
}
