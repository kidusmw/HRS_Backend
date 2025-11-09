<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Http\Requests\SuperAdmin\StoreUserRequest;
use App\Http\Requests\SuperAdmin\UpdateUserRequest;
use App\Services\AuditLogger;

class UserController extends Controller
{
    /**
     * Get all users
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * This method returns a paginated list of users.
     * The users are filtered by the following criteria:
     * - Search by name or email
     * - Filter by role
     * - Filter by hotel
     * - Filter by active status
     * 
     * The users are sorted by the following criteria:
     * - Name
     */
    public function index(Request $request)
    {
        $query = User::with('hotel');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->string('role')->toString()) {
            // Normalize super_admin to superadmin for enum
            $roleEnum = match($role) {
                'super_admin' => UserRole::SUPERADMIN,
                default => UserRole::tryFrom($role),
            };
            if ($roleEnum) {
                $query->where('role', $roleEnum);
            }
        }

        if ($hotelId = $request->integer('hotelId')) {
            $query->where('hotel_id', $hotelId);
        }

        if (!is_null($request->input('active'))) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Create a new user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * This method creates a new user.
     * The user is created with the following data:
     * - Name
     * - Email
     * - Password
     * - Role
     * - Hotel ID
     * - Active status
     * 
     * The password is generated if not provided.
     * The email is verified at the time of creation.
     * The user is returned in the response.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        // Normalize role: super_admin -> superadmin
        if (isset($validated['role']) && $validated['role'] === 'super_admin') {
            $validated['role'] = 'superadmin';
        }
        $validated['role'] = UserRole::from($validated['role']);

        // Generate password only if generatePassword is true
        $generatePassword = $validated['generatePassword'] ?? false;
        if ($generatePassword) {
            $validated['password'] = \Illuminate\Support\Str::random(12);
        } else {
            // Password is required (validated by Form Request), hash it
            $validated['password'] = \Hash::make($validated['password']);
        }

        // Remove generatePassword from validated data as it's not a database field
        unset($validated['generatePassword']);

        $validated['email_verified_at'] = now();

        $user = User::create($validated);
        
        // If user is an admin and assigned to a hotel, sync with hotel's primary_admin_id
        if ($user->role === \App\Enums\UserRole::ADMIN && $user->hotel_id) {
            $hotel = \App\Models\Hotel::find($user->hotel_id);
            if ($hotel && !$hotel->primary_admin_id) {
                // If hotel has no primary admin, make this user the primary admin
                $hotel->primary_admin_id = $user->id;
                $hotel->save();
            }
        }

        AuditLogger::logUserCreated($user, auth()->user());
        $user->load('hotel');

        return new UserResource($user);
    }

    /**
     * Get a user by ID
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * This method returns a user by ID.
     * The user is returned in the response.
     */
    public function show(int $id)
    {
        $user = User::with('hotel')->findOrFail($id);
        return new UserResource($user);
    }


    /**
     * Update a user
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * This method updates a user.
     * The user is updated with the following data:
     * - Name
     * - Email
     * - Password
     * - Role
     * - Hotel ID
     * - Active status
     * 
     * The password is updated if provided.
     * The user is returned in the response.
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        $user = User::findOrFail($id);
        $original = $user->getAttributes();

        $validated = $request->validated();

        // Normalize role
        if (isset($validated['role']) && $validated['role'] === 'super_admin') {
            $validated['role'] = 'superadmin';
        }
        if (isset($validated['role'])) {
            $validated['role'] = UserRole::from($validated['role']);
        }

        // Track the old hotel_id before updating
        $oldHotelId = $user->hotel_id;
        
        // Check if hotel_id is being updated (check request directly, not just validated, since null might not be in validated)
        $hotelIdChanged = false;
        $newHotelId = null;
        if ($request->has('hotel_id')) {
            $hotelIdChanged = true;
            $newHotelId = $request->input('hotel_id');
            // Ensure hotel_id is in validated array for fill()
            if ($newHotelId === null || $newHotelId === '') {
                $validated['hotel_id'] = null;
            } else {
                $validated['hotel_id'] = (int) $newHotelId;
            }
        }
        
        $changes = array_intersect_key($validated, $original);
        $user->fill($validated);
        if (array_key_exists('password', $validated) && $validated['password']) {
            $user->password = $validated['password'];
        }
        $user->save();
        
        // If user is an admin and hotel_id changed, sync with hotel's primary_admin_id
        if ($user->role === \App\Enums\UserRole::ADMIN && $hotelIdChanged) {
            // If user is being unassigned from hotel (hotel_id set to null)
            // Clear primary_admin_id from ALL hotels where this user is the primary admin
            if ($newHotelId === null || $newHotelId === '') {
                $hotelsWithThisAdmin = \App\Models\Hotel::where('primary_admin_id', $user->id)->get();
                foreach ($hotelsWithThisAdmin as $hotel) {
                    $hotel->primary_admin_id = null;
                    $hotel->save();
                }
            } else {
                // User is being assigned to a hotel (or reassigned to a different hotel)
                $newHotelIdInt = (int) $newHotelId;
                
                // If user was primary admin of old hotel, unassign them from that hotel
                if ($oldHotelId && $oldHotelId !== $newHotelIdInt) {
                    $oldHotel = \App\Models\Hotel::where('primary_admin_id', $user->id)
                        ->where('id', $oldHotelId)
                        ->first();
                    if ($oldHotel) {
                        $oldHotel->primary_admin_id = null;
                        $oldHotel->save();
                    }
                }
                
                // If user is assigned to a new hotel, make them the primary admin if no primary admin exists
                $newHotel = \App\Models\Hotel::find($newHotelIdInt);
                if ($newHotel && !$newHotel->primary_admin_id) {
                    $newHotel->primary_admin_id = $user->id;
                    $newHotel->save();
                }
            }
        }
        $user->load('hotel');

        AuditLogger::logUserUpdated($user, $changes, auth()->user());

        return new UserResource($user);
    }

    /**
     * Activate a user
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * This method activates a user.
     * The user is activated and returned in the response.
     */
    public function activate(int $id)
    {
        $user = User::findOrFail($id);
        $user->active = true;
        $user->save();
        return response()->json(['message' => 'User activated']);
    }

    /**
     * Deactivate a user
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * This method deactivates a user.
     * The user is deactivated and returned in the response.
     */
    public function deactivate(int $id)
    {
        $user = User::findOrFail($id);
        $user->active = false;
        $user->save();
        return response()->json(['message' => 'User deactivated']);
    }

    /**
     * Reset a user's password
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * This method resets a user's password.
     * The user's password is reset and returned in the response.
     */
    public function resetPassword(int $id)
    {
        $user = User::findOrFail($id);
        $new = \Str::password(12);
        $user->password = $new;
        $user->save();
        return response()->json(['message' => 'Password reset', 'password' => $new]);
    }
}


