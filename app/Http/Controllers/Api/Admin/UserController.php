<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all staff users for the admin's hotel
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Returns a paginated list of staff users (receptionist, manager) for the hotel.
     * Excludes admins and clients.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $query = User::where('hotel_id', $hotelId)
            ->whereIn('role', [UserRole::RECEPTIONIST, UserRole::MANAGER])
            ->with(['hotel', 'supervisor']);

        // Search by name or email
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($role = $request->string('role')->toString()) {
            $roleEnum = UserRole::tryFrom($role);
            if ($roleEnum && in_array($roleEnum, [UserRole::RECEPTIONIST, UserRole::MANAGER])) {
                $query->where('role', $roleEnum);
            }
        }

        // Filter by active status
        if (!is_null($request->input('active'))) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Get a specific staff user
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $staffUser = User::where('id', $id)
            ->where('hotel_id', $hotelId)
            ->whereIn('role', [UserRole::RECEPTIONIST, UserRole::MANAGER])
            ->with(['hotel', 'supervisor'])
            ->firstOrFail();

        return new UserResource($staffUser);
    }

    /**
     * Create a new staff user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        // Validate input and restrict to staff roles only
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|in:manager,receptionist',
            'phoneNumber' => 'nullable|string|max:20',
            'active' => 'sometimes|boolean',
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($hotelId) {
                    $query->where('hotel_id', $hotelId)->where('role', UserRole::MANAGER);
                }),
            ],
        ]);

        if ($validated['role'] === 'receptionist' && empty($validated['supervisor_id'])) {
            return response()->json(['message' => 'Supervisor is required for receptionists'], 422);
        }

        // Generate password if not provided
        $password = $validated['password'] ?? Str::random(12);

        // Map string input to enum
        $roleMap = [
            'manager' => UserRole::MANAGER,
            'receptionist' => UserRole::RECEPTIONIST,
        ];

        $staffUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $password,
            'role' => $roleMap[$validated['role']],
            'hotel_id' => $hotelId,
            'phone_number' => $validated['phoneNumber'] ?? null,
            'active' => $validated['active'] ?? true,
            'email_verified_at' => now(),
            'supervisor_id' => $validated['role'] === 'receptionist'
                ? ($validated['supervisor_id'] ?? null)
                : null,
        ]);

        $staffUser->load(['hotel', 'supervisor']);

        return (new UserResource($staffUser))->response()->setStatusCode(201);
    }

    /**
     * Update a staff user
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $staffUser = User::where('id', $id)
            ->where('hotel_id', $hotelId)
            ->whereIn('role', [UserRole::RECEPTIONIST, UserRole::MANAGER])
            ->firstOrFail();

        // Validate input
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|string|in:manager,receptionist',
            'phoneNumber' => 'nullable|string|max:20',
            'active' => 'sometimes|boolean',
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($hotelId) {
                    $query->where('hotel_id', $hotelId)->where('role', UserRole::MANAGER);
                }),
            ],
        ]);

        $roleForValidation = $validated['role'] ?? $staffUser->role->value;
        if ($roleForValidation === 'receptionist' && empty($validated['supervisor_id']) && !$staffUser->supervisor_id) {
            return response()->json(['message' => 'Supervisor is required for receptionists'], 422);
        }

        // Update fields
        if (isset($validated['name'])) {
            $staffUser->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $staffUser->email = $validated['email'];
        }
        if (isset($validated['password']) && !empty($validated['password'])) {
            $staffUser->password = $validated['password'];
        }
        if (isset($validated['role'])) {
            $roleMap = [
                'manager' => UserRole::MANAGER,
                'receptionist' => UserRole::RECEPTIONIST,
            ];
            $staffUser->role = $roleMap[$validated['role']];
        }
        if (isset($validated['phoneNumber'])) {
            $staffUser->phone_number = $validated['phoneNumber'];
        }
        if (isset($validated['active'])) {
            $staffUser->active = $validated['active'];
        }
        // Assign supervisor: only for receptionists; null otherwise
        if ($staffUser->role === UserRole::RECEPTIONIST) {
            // Prevent self-supervision
            if (!empty($validated['supervisor_id']) && $validated['supervisor_id'] == $staffUser->id) {
                return response()->json(['message' => 'User cannot supervise themselves'], 422);
            }
            $staffUser->supervisor_id = $validated['supervisor_id'] ?? $staffUser->supervisor_id;
        } else {
            $staffUser->supervisor_id = null;
        }

        $staffUser->save();
        $staffUser->load(['hotel', 'supervisor']);

        return new UserResource($staffUser);
    }

    /**
     * Delete a staff user
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel'
            ], 400);
        }

        $staffUser = User::where('id', $id)
            ->where('hotel_id', $hotelId)
            ->whereIn('role', [UserRole::RECEPTIONIST, UserRole::MANAGER])
            ->firstOrFail();

        $staffUser->delete();

        return response()->json([
            'message' => 'Staff user deleted successfully'
        ], 200);
    }
}
