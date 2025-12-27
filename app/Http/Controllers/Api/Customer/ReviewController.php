<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Customer\StoreCustomerReviewRequest;

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

    /**
     * Get the authenticated user's reviews (one per hotel)
     */
    public function mine(Request $request)
    {
        $user = Auth::user();

        $reviews = Review::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $reviews->map(function ($review) {
                return [
                    'id' => (string) $review->id,
                    'hotelId' => (string) $review->hotel_id,
                    'rating' => (int) $review->rating,
                    'comment' => $review->review ?? '',
                    'createdAt' => optional($review->created_at)->toIso8601String(),
                    'updatedAt' => optional($review->updated_at)->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Store a new review (only allowed after a checked_out reservation for the hotel)
     */
    public function store(StoreCustomerReviewRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();

        try {
            $review = Review::create([
                'hotel_id' => (int) $validated['hotel_id'],
                'user_id' => $user->id,
                'rating' => (int) $validated['rating'],
                'review' => (string) $validated['review'],
            ]);
        } catch (QueryException $e) {
            // Race-condition safety: unique (hotel_id, user_id)
            return response()->json([
                'message' => 'You have already submitted a review for this hotel.',
            ], 409);
        }

        return response()->json([
            'message' => 'Review submitted successfully.',
            'data' => [
                'id' => (string) $review->id,
                'hotelId' => (string) $review->hotel_id,
                'rating' => (int) $review->rating,
                'comment' => $review->review ?? '',
                'createdAt' => optional($review->created_at)->toIso8601String(),
                'updatedAt' => optional($review->updated_at)->toIso8601String(),
            ],
        ], 201);
    }
}
