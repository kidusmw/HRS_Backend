<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Enums\UserRole;

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

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
