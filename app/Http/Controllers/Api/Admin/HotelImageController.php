<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHotelImageRequest;
use App\Http\Requests\Admin\UpdateHotelImageRequest;
use App\Http\Resources\HotelImageResource;
use App\Models\HotelImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HotelImageController extends Controller
{
    /**
     * List images for the authenticated admin's hotel.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel',
            ], 400);
        }

        $query = HotelImage::where('hotel_id', $hotelId);

        if ($request->boolean('only_active')) {
            $query->where('is_active', true);
        }

        // per_page is for the number of images to return per page
        $perPage = (int) ($request->input('per_page', 15));

        $images = $query
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate($perPage);

        return HotelImageResource::collection($images);
    }

    /**
     * Store one or more images for the authenticated admin's hotel.
     */
    public function store(StoreHotelImageRequest $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel',
            ], 400);
        }

        $hotel = $user->hotel;
        $files = $request->file('images', []);

        $altTexts = $request->input('alt_text', []);
        $displayOrders = $request->input('display_order', []);
        $isActives = $request->input('is_active', []);

        // baseQuery is for the base query to get the max display order and existing count
        $baseQuery = HotelImage::where('hotel_id', $hotelId);
        // maxOrder is for the max display order
        $maxOrder = (int) $baseQuery->max('display_order') ?: 0;
        // existingCount is for the existing count of images
        $existingCount = (int) $baseQuery->count();

        $created = [];

        foreach ($files as $index => $file) {
            $extension = $file->getClientOriginalExtension() ?: 'jpg';

            $hotelName = $hotel?->name ?? ('hotel_' . $hotelId);
            $hotelSlug = Str::slug($hotelName, '_');

            $sequence = $existingCount + $index + 1;
            $fileName = "{$hotelSlug}_{$sequence}.{$extension}";

            $path = $file->storeAs("hotel-images/{$hotelId}", $fileName, 'public');

            $displayOrder = $displayOrders[$index] ?? null;
            if ($displayOrder === null || !is_numeric($displayOrder)) {
                $displayOrder = ++$maxOrder;
            }

            $isActive = $isActives[$index] ?? true;

            $image = HotelImage::create([
                'hotel_id' => $hotelId,
                'image_url' => $path,
                'alt_text' => $altTexts[$index] ?? null,
                'display_order' => (int) $displayOrder,
                'is_active' => (bool) $isActive,
            ]);

            $created[] = $image;
        }

        // return the created images
        // collect is for the collection of the created images
        // response is for the response of the created images
        // setStatusCode is for the status code of the response
        return HotelImageResource::collection(collect($created))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update metadata for a hotel image.
     */
    public function update(UpdateHotelImageRequest $request, int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel',
            ], 400);
        }

        $image = HotelImage::where('hotel_id', $hotelId)->findOrFail($id);

        $validated = $request->validated();

        if (array_key_exists('alt_text', $validated)) {
            $image->alt_text = $validated['alt_text'];
        }

        if (array_key_exists('display_order', $validated)) {
            $image->display_order = $validated['display_order'];
        }

        if (array_key_exists('is_active', $validated)) {
            $image->is_active = $validated['is_active'];
        }

        $image->save();

        // return the updated image
        return new HotelImageResource($image);
    }

    /**
     * Delete a hotel image and its underlying file.
     */
    public function destroy(int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json([
                'message' => 'User is not associated with a hotel',
            ], 400);
        }

        $image = HotelImage::where('hotel_id', $hotelId)->findOrFail($id);

        // if the image url exists and the image url is in the public storage, delete the image
        if ($image->image_url && Storage::disk('public')->exists($image->image_url)) {
            Storage::disk('public')->delete($image->image_url);
        }

        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully',
        ]);
    }
}


