<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Enums\UserRole;
use App\Http\Resources\HotelResource;
use App\Http\Requests\SuperAdmin\StoreHotelRequest;
use App\Http\Requests\SuperAdmin\UpdateHotelRequest;
use App\Services\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class HotelController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $query = Hotel::with('primaryAdmin');

        /**
         * Search by name or address
         * Filter by timezone
         * Filter by has admin
         * Paginate the results
         * Return the results as a HotelResource collection
         */
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($timezone = $request->string('timezone')->toString()) {
            $query->where('timezone', $timezone);
        }

        if ($request->boolean('hasAdmin')) {
            $query->whereNotNull('primary_admin_id');
        }

        $hotels = $query->paginate($request->integer('per_page', 15));
        return HotelResource::collection($hotels);
    }

    public function store(StoreHotelRequest $request)
    {
        $validated = $request->validated();
        
        // Handle empty string from FormData for primary_admin_id
        if (isset($validated['primary_admin_id']) && $validated['primary_admin_id'] === '') {
            $validated['primary_admin_id'] = null;
        } elseif (isset($validated['primary_admin_id'])) {
            $validated['primary_admin_id'] = (int) $validated['primary_admin_id'];
        }

        $hotel = Hotel::create($validated);
        
        // Load primaryAdmin relationship for response
        $hotel->load('primaryAdmin');

        AuditLogger::logHotelCreated($hotel, auth()->user());

        return new HotelResource($hotel);
    }

    public function show(int $id)
    {
        $hotel = Hotel::with('primaryAdmin')->findOrFail($id);
        return new HotelResource($hotel);
    }

    public function update(UpdateHotelRequest $request, int $id)
    {
        try {
            $hotel = Hotel::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Hotel not found for update', [
                'hotel_id' => $id,
                'request_data' => $request->all(),
            ]);
            
            return response()->json([
                'message' => 'Hotel not found',
                'error' => "Hotel with ID {$id} does not exist"
            ], 404);
        }
        
        $original = $hotel->getAttributes();

        $validated = $request->validated();

        // Handle primary_admin_id - check request directly for JSON requests
        if ($request->has('primary_admin_id')) {
            $primaryAdminId = $request->input('primary_admin_id');
            if ($primaryAdminId === '' || $primaryAdminId === null) {
                $validated['primary_admin_id'] = null;
            } else {
                // Validate that the user exists and is an admin
                $user = \App\Models\User::find($primaryAdminId);
                if ($user && $user->role === \App\Enums\UserRole::ADMIN) {
                    $validated['primary_admin_id'] = (int) $primaryAdminId;
                }
            }
        } elseif (isset($validated['primary_admin_id'])) {
            // Fallback to validated value if present
            if ($validated['primary_admin_id'] === '' || $validated['primary_admin_id'] === null) {
                $validated['primary_admin_id'] = null;
            } else {
                $validated['primary_admin_id'] = (int) $validated['primary_admin_id'];
            }
        }

        $changes = array_intersect_key($validated, $original);
        $hotel->fill($validated);
        $hotel->save();
        
        // Refresh the hotel from database and reload primaryAdmin relationship
        $hotel->refresh();
        $hotel->load('primaryAdmin');
        
        // Debug log to verify the save
        \Log::info('Hotel updated', [
            'hotel_id' => $hotel->id,
            'primary_admin_id' => $hotel->primary_admin_id,
            'primary_admin_name' => $hotel->primaryAdmin?->name,
        ]);

        AuditLogger::logHotelUpdated($hotel, $changes, auth()->user());

        return new HotelResource($hotel);
    }
    
    public function destroy(int $id)
    {
        try {
            $hotel = Hotel::with(['rooms', 'settings', 'users'])->findOrFail($id);
            
            // Authorize deletion
            $this->authorize('delete', $hotel);
            
            // Store hotel info before deletion for logging
            $hotelId = $hotel->id;
            $hotelName = $hotel->name;
            
            // Manually handle deletion of related records to ensure proper cleanup
            // This is more reliable than relying solely on database cascades
            
            // 1. Delete all reservations through rooms (cascade through rooms)
            $roomIds = $hotel->rooms()->pluck('id');
            if ($roomIds->isNotEmpty()) {
                Reservation::whereIn('room_id', $roomIds)->delete();
            }
            
            // 2. Delete all rooms (cascade should handle this, but doing it explicitly)
            $hotel->rooms()->delete();
            
            // 3. Delete hotel settings (cascade should handle this)
            $hotel->settings()->delete();
            
            // 4. Unassign all users from this hotel (nullOnDelete should handle this)
            // But we'll do it explicitly to be safe
            $hotel->users()->update(['hotel_id' => null]);
            
            // 5. Log the deletion BEFORE deleting the hotel (so foreign key constraint is satisfied)
            // We store hotel_id and hotel_name in meta for reference
            AuditLogger::log('hotel.deleted', auth()->user(), $hotelId, [
                'hotel_id' => $hotelId,
                'hotel_name' => $hotelName,
            ]);
            
            // Now delete the hotel
            $hotel->delete();

            return response()->json(['message' => 'Hotel deleted successfully']);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database constraint violations
            \Log::error('Hotel deletion failed', [
                'hotel_id' => $id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            
            return response()->json([
                'message' => 'Cannot delete hotel due to existing relationships',
                'error' => config('app.debug') ? $e->getMessage() : 'Please ensure all related records can be removed'
            ], 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized to delete this hotel'
            ], 403);
        } catch (\Exception $e) {
            \Log::error('Hotel deletion error', [
                'hotel_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Failed to delete hotel',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
}


