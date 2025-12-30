<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Return the authenticated user's profile.
     */
    public function show(Request $request): UserResource
    {
        $user = $request->user()->load('hotel');

        return new UserResource($user);
    }

    /**
     * Update the authenticated user's profile (name, email, phone, avatar).
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $validated = $request->validated();

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (array_key_exists('phoneNumber', $validated)) {
            $user->phone_number = $validated['phoneNumber'] ?: null;
        }

        // Remove existing avatar if requested
        if (!empty($validated['removeAvatar']) && $user->avatar_path) {
            Media::deleteIfPresent($user->avatar_path);
            $user->avatar_path = null;
        }

        // Replace avatar if a new file is provided
        if ($request->hasFile('avatar')) {
            logger()->info('ProfileController: avatar upload detected', [
                'user_id' => $user->id,
                'original_name' => $request->file('avatar')->getClientOriginalName(),
                'mime' => $request->file('avatar')->getMimeType(),
                'size' => $request->file('avatar')->getSize(),
            ]);
            if ($user->avatar_path) {
                Media::deleteIfPresent($user->avatar_path);
            }
            $path = $request->file('avatar')->store('avatars', Media::diskName());
            $user->avatar_path = $path;
        } else {
            logger()->info('ProfileController: no avatar file present', [
                'user_id' => $user->id,
                'hasFile_avatar' => $request->hasFile('avatar'),
                'input_keys' => array_keys($request->all()),
            ]);
        }

        $user->save();
        $user->load('hotel');

        return new UserResource($user);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (!Hash::check($validated['currentPassword'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->password = $validated['newPassword'];
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }
}

