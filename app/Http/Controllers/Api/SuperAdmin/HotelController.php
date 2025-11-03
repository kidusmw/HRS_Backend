<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Hotel;
use App\Enums\UserRole;
use App\Http\Resources\HotelResource;
use App\Http\Requests\SuperAdmin\StoreHotelRequest;
use App\Http\Requests\SuperAdmin\UpdateHotelRequest;
use App\Services\AuditLogger;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        $query = Hotel::with('users');

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
            $query->whereHas('users', fn($q) => $q->where('role', UserRole::ADMIN));
        }

        $hotels = $query->paginate($request->integer('per_page', 15));
        return HotelResource::collection($hotels);
    }

    public function store(StoreHotelRequest $request)
    {
        $validated = $request->validated();

        $hotel = Hotel::create($validated);
        $hotel->load('users');

        AuditLogger::logHotelCreated($hotel, auth()->user());

        return new HotelResource($hotel);
    }

    public function show(int $id)
    {
        $hotel = Hotel::with('users')->findOrFail($id);
        return new HotelResource($hotel);
    }

    public function update(UpdateHotelRequest $request, int $id)
    {
        $hotel = Hotel::findOrFail($id);
        $original = $hotel->getAttributes();

        $validated = $request->validated();

        $changes = array_intersect_key($validated, $original);
        $hotel->fill($validated);
        $hotel->save();
        $hotel->load('users');

        AuditLogger::logHotelUpdated($hotel, $changes, auth()->user());

        return new HotelResource($hotel);
    }
    
    public function destroy(int $id)
    {
        $hotel = Hotel::findOrFail($id);
        $hotel->delete();

        AuditLogger::log('hotel.deleted', auth()->user(), $hotel->id, [
            'hotel_id' => $hotel->id,
            'hotel_name' => $hotel->name,
        ]);

        return response()->json(['message' => 'Hotel deleted']);
    }
}


