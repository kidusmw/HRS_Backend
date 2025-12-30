<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomImageRequest;
use App\Http\Requests\Admin\UpdateRoomImageRequest;
use App\Http\Resources\RoomImageResource;
use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Support\Media;

class RoomImageController extends Controller
{
    /**
     * List images for a room (hotel-scoped).
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json(['message' => 'User is not associated with a hotel'], 400);
        }

        $roomId = $request->input('room_id');
        if (!$roomId) {
            return response()->json(['message' => 'room_id is required'], 400);
        }

        $room = Room::where('hotel_id', $hotelId)->findOrFail($roomId);

        $perPage = (int) $request->input('per_page', 15);

        $images = RoomImage::where('room_id', $room->id)
            ->when($request->boolean('only_active'), fn($q) => $q->where('is_active', true))
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate($perPage);

        return RoomImageResource::collection($images);
    }

    /**
     * Store one or more images for a room (hotel-scoped).
     */
    public function store(StoreRoomImageRequest $request)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json(['message' => 'User is not associated with a hotel'], 400);
        }

        $roomId = (int) $request->input('room_id');

        $room = Room::where('hotel_id', $hotelId)->findOrFail($roomId);

        $files = $request->file('images', []);
        $altTexts = $request->input('alt_text', []);
        $displayOrders = $request->input('display_order', []);
        $isActives = $request->input('is_active', []);

        $baseQuery = RoomImage::where('room_id', $room->id);
        $maxOrder = (int) $baseQuery->max('display_order') ?: 0;
        $existingCount = (int) $baseQuery->count();

        $created = [];

        foreach ($files as $index => $file) {
            $extension = $file->getClientOriginalExtension() ?: 'jpg';

            $roomName = $room->type ?? ('room_' . $room->id);
            $roomSlug = Str::slug($roomName, '_');

            $sequence = $existingCount + $index + 1;
            $fileName = "{$roomSlug}_{$sequence}.{$extension}";

            $path = $file->storeAs("room-images/{$room->id}", $fileName, Media::diskName());

            $displayOrder = $displayOrders[$index] ?? null;
            if ($displayOrder === null || !is_numeric($displayOrder)) {
                $displayOrder = ++$maxOrder;
            }

            $isActive = $isActives[$index] ?? true;

            $image = RoomImage::create([
                'room_id' => $room->id,
                'image_url' => $path,
                'alt_text' => $altTexts[$index] ?? null,
                'display_order' => (int) $displayOrder,
                'is_active' => (bool) $isActive,
            ]);

            $created[] = $image;
        }

        return RoomImageResource::collection(collect($created))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update metadata for a room image.
     */
    public function update(UpdateRoomImageRequest $request, int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json(['message' => 'User is not associated with a hotel'], 400);
        }

        $image = RoomImage::findOrFail($id);
        $room = Room::where('hotel_id', $hotelId)->findOrFail($image->room_id);

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

        return new RoomImageResource($image);
    }

    /**
     * Delete a room image and its underlying file.
     */
    public function destroy(int $id)
    {
        $user = auth()->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json(['message' => 'User is not associated with a hotel'], 400);
        }

        $image = RoomImage::findOrFail($id);
        $room = Room::where('hotel_id', $hotelId)->findOrFail($image->room_id);

        Media::deleteIfPresent($image->image_url);

        $image->delete();

        return response()->json(['message' => 'Image deleted successfully']);
    }
}


