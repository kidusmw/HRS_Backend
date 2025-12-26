<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Get reviews for a hotel (public, no auth required)
     */
    public function index(Request $request, int $hotelId)
    {
        // Verify hotel exists
        $hotel = Hotel::findOrFail($hotelId);

        $perPage = (int) $request->input('per_page', 15);

        $reviews = Review::with('user')
            ->where('hotel_id', $hotelId)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $reviews->getCollection()->map(function ($review) {
                return [
                    'id' => (string) $review->id,
                    'hotelId' => (string) $review->hotel_id,
                    'userName' => $review->user->name ?? 'Anonymous',
                    'rating' => (float) $review->rating,
                    'date' => $review->created_at->toDateString(),
                    'comment' => $review->review ?? '',
                ];
            }),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }
}
