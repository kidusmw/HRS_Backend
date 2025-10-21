<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This middleware validates the authenticated user's current role from the database
     * and the account state (active and email verified) before allowing access.
     * It does not trust token abilities alone and fails closed on any uncertainty.
     */
    public function handle(Request $request, Closure $next, ...$allowedRoles): Response
    {
        $user = $request->user();

        // If a Bearer token is explicitly provided, prefer validating using that token
        // to avoid test harness/session-based identities (e.g., actingAs) taking precedence.
        $authHeader = $request->header('Authorization');
        if (is_string($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
            $plainTextToken = substr($authHeader, 7);
            $accessToken = $plainTextToken !== '' ? PersonalAccessToken::findToken($plainTextToken) : null;
            if (!$accessToken) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            $user = $accessToken->tokenable;
        }

        // Fail closed if not authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Enforce account state from DB (defense-in-depth)
        if (!$user->active) {
            return response()->json(['message' => 'User account is inactive'], 403);
        }

        // Require verified email if your app uses verification
        // We intentionally check the timestamp directly to avoid coupling to UI flow
        if (method_exists($user, 'hasVerifiedEmail')) {
            if (!$user->hasVerifiedEmail()) {
                return response()->json(['message' => 'Email not verified'], 403);
            }
        } else {
            if (is_null($user->email_verified_at)) {
                return response()->json(['message' => 'Email not verified'], 403);
            }
        }

        // Normalize allowed roles to lowercase strings
        $normalizedAllowed = array_map(static function ($role) {
            return strtolower((string) $role);
        }, $allowedRoles);

        // Resolve current role name from enum or string and normalize
        $currentRoleName = null;
        try {
            if (is_object($user->role) && property_exists($user->role, 'name')) {
                $currentRoleName = strtolower($user->role->name);
            } else {
                $currentRoleName = strtolower((string) $user->role);
            }
        } catch (\Throwable $e) {
            // Fail closed on any uncertainty resolving role
            return response()->json(['message' => 'Authorization failed'], 403);
        }

        if (!in_array($currentRoleName, $normalizedAllowed, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Authorization is enforced by DB-backed role only for these routes.
        // Do not rely on token abilities for allow decisions to avoid false positives.

        return $next($request);
    }
}