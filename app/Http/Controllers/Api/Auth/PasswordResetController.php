<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Send a password reset link to the given email.
     * Endpoint: POST /api/password/forgot
     * Body: { email: string }
     * 
     * Security: Always returns identical response regardless of email existence
     * to prevent user enumeration attacks.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Rate limiting to prevent abuse
        $key = 'password-reset:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many password reset attempts. Please try again in ' . $seconds . ' seconds.'
            ], 429);
        }

        // Always return identical response to prevent user enumeration
        // Laravel's Password::sendResetLink() handles internal validation securely
        Password::sendResetLink(['email' => $validated['email']]);

        // Increment rate limiter
        RateLimiter::hit($key, 300); // 5 minutes

        // Generic response regardless of whether email exists
        return response()->json([
            'message' => 'If your email exists in our records, you will receive a password reset link.'
        ]);
    }

    /**
     * Reset the given user's password.
     * Endpoint: POST /api/password/reset
     * Body: { token: string, email: string, password: string, password_confirmation: string }
     * 
     * Security: Returns generic error messages to prevent user enumeration
     * and token information leakage.
     */
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function ($user) use ($validated) {
                // Update the user's password
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all existing personal access tokens (Sanctum)
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Your password has been successfully reset.',
            ]);
        }

        // Generic error message for all failure cases to prevent information leakage
        return response()->json([
            'message' => 'This password reset link is invalid or has expired.',
        ], 400);
    }
}


