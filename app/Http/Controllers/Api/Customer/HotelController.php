<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HotelController extends Controller
{
    /**
     * Get list of hotels (public, no auth required)
     */
    public function index(Request $request)
    {
        $query = Hotel::with(['images' => function ($q) {
            $q->where('is_active', true)->orderBy('display_order');
        }]);

        // Search by name, city, or country
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        // Filter by city
        if ($city = $request->string('city')->toString()) {
            $query->where('city', 'like', "%{$city}%");
        }

        // Filter by country
        if ($country = $request->string('country')->toString()) {
            $query->where('country', 'like', "%{$country}%");
        }

        // Get review summaries for all hotels
        $hotels = $query->get();
        $hotelIds = $hotels->pluck('id');

        $reviewSummaries = DB::table('reviews')
            ->whereIn('hotel_id', $hotelIds)
            ->select('hotel_id', DB::raw('AVG(rating) as average_rating'), DB::raw('COUNT(*) as total_reviews'))
            ->groupBy('hotel_id')
            ->get()
            ->keyBy('hotel_id');

        // Sort
        $sort = $request->string('sort', 'rating-desc')->toString();
        $hotels = $hotels->map(function ($hotel) use ($reviewSummaries) {
            $summary = $reviewSummaries->get($hotel->id);
            $hotel->reviewSummary = [
                'averageRating' => $summary ? (float) $summary->average_rating : 0.0,
                'totalReviews' => $summary ? (int) $summary->total_reviews : 0,
            ];
            return $hotel;
        });

        // Apply sorting
        if ($sort === 'price-asc') {
            // Get min price per hotel from rooms
            $minPrices = Room::whereIn('hotel_id', $hotelIds)
                ->where('status', \App\Enums\RoomStatus::AVAILABLE)
                ->select('hotel_id', DB::raw('MIN(price) as min_price'))
                ->groupBy('hotel_id')
                ->pluck('min_price', 'hotel_id');
            
            $hotels = $hotels->sortBy(function ($hotel) use ($minPrices) {
                return $minPrices->get($hotel->id, PHP_INT_MAX);
            })->values();
        } elseif ($sort === 'price-desc') {
            $minPrices = Room::whereIn('hotel_id', $hotelIds)
                ->where('status', \App\Enums\RoomStatus::AVAILABLE)
                ->select('hotel_id', DB::raw('MIN(price) as min_price'))
                ->groupBy('hotel_id')
                ->pluck('min_price', 'hotel_id');
            
            $hotels = $hotels->sortByDesc(function ($hotel) use ($minPrices) {
                return $minPrices->get($hotel->id, 0);
            })->values();
        } else {
            // Default: rating-desc
            $hotels = $hotels->sortByDesc(function ($hotel) {
                return $hotel->reviewSummary['averageRating'];
            })->values();
        }

        // Paginate manually (since we're using collection sorting)
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);
        $total = $hotels->count();
        $items = $hotels->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $items->map(function ($hotel) {
                return $this->transformHotelListItem($hotel);
            }),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Get hotel detail by ID (public, no auth required)
     */
    public function show(int $id)
    {
        $hotel = Hotel::with([
            'images' => function ($q) {
                $q->where('is_active', true)->orderBy('display_order');
            },
            'rooms' => function ($q) {
                $q->where('status', \App\Enums\RoomStatus::AVAILABLE);
            },
            'rooms.images' => function ($q) {
                $q->where('is_active', true)->orderBy('display_order');
            },
        ])->findOrFail($id);

        // Get review summary
        $reviewSummary = DB::table('reviews')
            ->where('hotel_id', $id)
            ->select(DB::raw('AVG(rating) as average_rating'), DB::raw('COUNT(*) as total_reviews'))
            ->first();

        // Group rooms by type
        $roomTypes = $hotel->rooms->groupBy('type')->map(function ($rooms, $type) {
            $firstRoom = $rooms->first();
            $images = $firstRoom->images->take(3)->map(function ($img) {
                $url = Storage::disk('public')->url($img->image_url);
                if (!str_starts_with($url, 'http')) {
                    $appUrl = rtrim(config('app.url'), '/');
                    $url = $appUrl . $url;
                }
                return $url;
            })->values();

            return [
                'type' => $type,
                'priceFrom' => (float) $rooms->min('price'),
                'description' => $firstRoom->description,
                'images' => $images,
            ];
        })->values();

        return response()->json([
            'id' => $hotel->id,
            'name' => $hotel->name,
            'city' => $hotel->city,
            'country' => $hotel->country,
            'address' => "{$hotel->city}, {$hotel->country}", // Derived from city + country
            'description' => $hotel->description,
            'images' => $hotel->images->map(function ($img) {
                $url = Storage::disk('public')->url($img->image_url);
                if (!str_starts_with($url, 'http')) {
                    $appUrl = rtrim(config('app.url'), '/');
                    $url = $appUrl . $url;
                }
                return $url;
            })->values(),
            'roomTypes' => $roomTypes,
            'reviewSummary' => [
                'averageRating' => $reviewSummary ? (float) $reviewSummary->average_rating : 0.0,
                'totalReviews' => $reviewSummary ? (int) $reviewSummary->total_reviews : 0,
            ],
        ]);
    }

    /**
     * Transform hotel for list view
     */
    private function transformHotelListItem(Hotel $hotel): array
    {
        // Get min price from available rooms
        $minPrice = Room::where('hotel_id', $hotel->id)
            ->where('status', \App\Enums\RoomStatus::AVAILABLE)
            ->min('price') ?? 0;

        return [
            'id' => (string) $hotel->id,
            'name' => $hotel->name,
            'city' => $hotel->city,
            'country' => $hotel->country,
            'address' => "{$hotel->city}, {$hotel->country}",
            'priceFrom' => (float) $minPrice,
            'rating' => $hotel->reviewSummary['averageRating'] ?? 0.0,
            'images' => $hotel->images->map(function ($img) {
                $url = Storage::disk('public')->url($img->image_url);
                if (!str_starts_with($url, 'http')) {
                    $appUrl = rtrim(config('app.url'), '/');
                    $url = $appUrl . $url;
                }
                return $url;
            })->values()->toArray(),
            'reviewSummary' => $hotel->reviewSummary ?? [
                'averageRating' => 0.0,
                'totalReviews' => 0,
            ],
        ];
    }
}
