<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\URL;
use App\Models\User;

class VerifyEmailController extends Controller
{
    /**
     * Handle email verification for API clients.
     * 
     * This endpoint validates the signed URL and email hash, then marks
     * the user's email as verified. No authentication required.
     */
    public function __invoke(Request $request, int $id, string $hash)
    {
        // Validate the signature on the URL
        if (! URL::hasValidSignature($request)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Invalid or expired verification link'
                ], 403);
            }
            return redirect()->to(env('FRONTEND_BASE_URL', 'http://localhost:5173') . '/verify?error=invalid_link');
        }

        $user = User::find($id);
        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }
            return redirect()->to(env('FRONTEND_BASE_URL', 'http://localhost:5173') . '/verify?error=user_not_found');
        }

        // Ensure the hash matches the user's current email
        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Invalid verification hash'
                ], 403);
            }
            return redirect()->to(env('FRONTEND_BASE_URL', 'http://localhost:5173') . '/verify?error=invalid_hash');
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Email already verified'
                ]);
            }
            return redirect()->to(env('FRONTEND_BASE_URL', 'http://localhost:5173') . '/verify?success=already_verified');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Email verified successfully'
            ]);
        }

        // Redirect to frontend verification page with success
        return redirect()->to(env('FRONTEND_BASE_URL', 'http://localhost:5173') . '/verify?success=verified');
    }
}
