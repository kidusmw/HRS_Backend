<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Enums\UserRole;
use App\Models\User;

class RegisterController extends Controller
{
    /**
     * Public registration for customers only
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phoneNumber' => 'required|string|regex:/^\+[1-9]\d{1,14}$/',
        ], 
        // Validation messages
        [
            'phoneNumber.required' => 'Phone number is required',
            'phoneNumber.regex' => 'Phone number must be in E.164 format (e.g., +251912345678)',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'phone_number' => $request->phoneNumber,
            'role' => UserRole::CLIENT,
        ]);

        // Always send verification email (works in all environments)
        $user->sendEmailVerificationNotification();

        // Issue granular abilities for customers
        $abilities = ['reservation.create', 'reservation.read', 'profile.update'];
        $token = $user->createToken('auth_token', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }
}
