<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHotelLogoRequest;
use App\Http\Resources\HotelLogoResource;
use App\Models\Hotel;
use App\Support\Media;

class HotelLogoController extends Controller
{
    /**
     * Get the current hotel's logo.
     */
    public function show()
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json(['message' => 'User is not associated with a hotel'], 400);
        }

        $hotel = Hotel::findOrFail($hotelId);
        return new HotelLogoResource($hotel);
    }

    /**
     * Upload/update the current hotel's logo.
     */
    public function store(StoreHotelLogoRequest $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json(['message' => 'User is not associated with a hotel'], 400);
        }

        $hotel = Hotel::findOrFail($hotelId);

        $file = $request->file('logo');
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'logo_' . $hotelId . '.' . $extension;
        $path = $file->storeAs("hotel-logos/{$hotelId}", $filename, Media::diskName());

        $hotel->logo_path = $path;
        $hotel->save();

        return (new HotelLogoResource($hotel))->response()->setStatusCode(201);
    }
}


