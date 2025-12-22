<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        $perPage = (int) $request->input('per_page', 10);
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = Reservation::query()
            ->with(['room', 'user'])
            ->whereHas('room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            });

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('check_in', '>=', Carbon::parse($dateFrom)->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('check_out', '<=', Carbon::parse($dateTo)->toDateString());
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('room', function ($sub) use ($search) {
                    $sub->where('type', 'like', "%{$search}%");
                })->orWhere('id', $search);
            });
        }

        $bookings = $query->orderByDesc('created_at')->paginate($perPage);

        // Calculate status counts for all bookings (not just current page)
        // This helps the frontend display accurate summary cards
        $statusCounts = [
            'pending' => Reservation::whereHas('room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            })->where('status', 'pending')->count(),
            'confirmed' => Reservation::whereHas('room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            })->where('status', 'confirmed')->count(),
            'checked_in' => Reservation::whereHas('room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            })->where('status', 'checked_in')->count(),
            'checked_out' => Reservation::whereHas('room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            })->where('status', 'checked_out')->count(),
            'cancelled' => Reservation::whereHas('room', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            })->where('status', 'cancelled')->count(),
        ];

        // Calculate total active bookings (pending + confirmed + checked_in)
        $totalActive = $statusCounts['pending'] + $statusCounts['confirmed'] + $statusCounts['checked_in'];

        return response()->json([
            'data' => $bookings->items(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'from' => $bookings->firstItem(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'to' => $bookings->lastItem(),
                'total' => $bookings->total(),
            ],
            'status_counts' => $statusCounts,
            'total_active' => $totalActive,
        ]);
    }
}

