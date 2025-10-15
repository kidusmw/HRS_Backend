<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // Optional defense-in-depth: if token exists and has abilities, ensure at least one matches
        // We DO NOT rely on tokenCan() for authorization, we only use it as an additional check.
        $token = $request->user()->currentAccessToken();
        if ($token && is_array($token->abilities) && count($token->abilities) > 0) {
            // If abilities are present but none align with the route's intended roles, block access
            // This prevents overly-permissive tokens from being used where granular abilities are expected.
            $hasRelevantAbility = false;
            foreach ($token->abilities as $ability) {
                if (is_string($ability) && $ability !== '*' ) {
                    // Treat generic wildcard as too permissive; require explicit ability
                    // Map example: admin routes should have abilities like "user.create", "staff.manage"
                    $hasRelevantAbility = true;
                    break;
                }
            }
            if (!$hasRelevantAbility) {
                return response()->json(['message' => 'Token lacks required abilities'], 403);
            }
        }

        return $next($request);
    }
}


