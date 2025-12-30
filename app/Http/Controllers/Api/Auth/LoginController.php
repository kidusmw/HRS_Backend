<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\AuditLogger;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->active) {
            return response()->json(['message' => 'User account is inactive'], 403);
        }

        // Enforce email verification prior to issuing tokens (prevents token use before verification)
        if (method_exists($user, 'hasVerifiedEmail')) {
            if (!$user->hasVerifiedEmail()) {
                return response()->json(['message' => 'Email not verified'], 403);
            }
        } else {
            if (is_null($user->email_verified_at)) {
                return response()->json(['message' => 'Email not verified'], 403);
            }
        }

        // Optional: rotate tokens on each login to reduce token reuse risk
        // (Deletes existing tokens for this user so a fresh token with accurate abilities is used)
        $user->tokens()->delete();

        // Map granular abilities by role; avoid using role names as abilities
        // Keep it simple and explicit for maintainability
        $abilities = [];
        switch ($user->role) {
            case UserRole::SUPERADMIN:
                $abilities = ['*']; // Full access; middleware still enforces DB role
                break;
            case UserRole::ADMIN:
                $abilities = [
                    'user.create', 'user.read', 'user.update', 'user.deactivate',
                    'staff.manage', 'reservation.manage', 'room.manage',
                ];
                break;
            case UserRole::MANAGER:
                $abilities = [
                    'reservation.manage', 'reservation.read', 'room.manage', 'report.read',
                ];
                break;
            case UserRole::RECEPTIONIST:
                $abilities = [
                    'reservation.create', 'reservation.read', 'reservation.update',
                    'checkin.process', 'checkout.process',
                ];
                break;
            case UserRole::CLIENT:
            default:
                $abilities = ['reservation.create', 'reservation.read', 'profile.update'];
                break;
        }

        // Create a new personal access token for the user with granular abilities
        $token = $user->createToken('auth_token', $abilities)->plainTextToken;

        // Log login action for attendance tracking
        AuditLogger::log('user.login', $user, $user->hotel_id, [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'login_time' => now()->toIso8601String(),
        ]);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function redirectToGoogle()
    {
        // Return the Google OAuth URL for the frontend to redirect to
        $redirectUrl = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        
        return response()->json([
            'redirect_url' => $redirectUrl
        ]);
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $email = $googleUser->getEmail();
            $name = $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User';

            if (!$email) {
                return response()->json(['message' => 'Unable to retrieve Google account email'], 400);
            }

            // Find or create user by email
            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Str::random(32),
                    // Phone is collected later via profile completion; reservation flows enforce presence.
                    'phone_number' => null,
                    'role' => UserRole::CLIENT,
                    'email_verified_at' => now(),
                    'active' => true,
                ]);
            } else {
                // Ensure account is active and verified
                if (is_null($user->email_verified_at)) {
                    $user->email_verified_at = now();
                    $user->save();
                }
                if (! $user->active) {
                    return response()->json(['message' => 'User account is inactive'], 403);
                }
            }

            // Rotate existing tokens
            $user->tokens()->delete();

            // Map abilities by role
            $abilities = [];
            switch ($user->role) {
                case UserRole::SUPERADMIN:
                    $abilities = ['*'];
                    break;
                case UserRole::ADMIN:
                    $abilities = [
                        'user.create', 'user.read', 'user.update', 'user.deactivate',
                        'staff.manage', 'reservation.manage', 'room.manage',
                    ];
                    break;
                case UserRole::MANAGER:
                    $abilities = [
                        'reservation.manage', 'reservation.read', 'room.manage', 'report.read',
                    ];
                    break;
                case UserRole::RECEPTIONIST:
                    $abilities = [
                        'reservation.create', 'reservation.read', 'reservation.update',
                        'checkin.process', 'checkout.process',
                    ];
                    break;
                case UserRole::CLIENT:
                default:
                    $abilities = ['reservation.create', 'reservation.read', 'profile.update'];
                    break;
            }

            $token = $user->createToken('auth_token', $abilities)->plainTextToken;

            // Log login action for attendance tracking
            AuditLogger::log('user.login', $user, $user->hotel_id, [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'login_time' => now()->toIso8601String(),
                'method' => 'google',
            ]);

            return response()->json([
                'message' => 'Google authentication successful',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Google authentication failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
