<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Reservation;
use App\Models\Room;
use App\Enums\RoomStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        // Handle case where manager doesn't have a hotel_id assigned
        if ($hotelId === null) {
            \Log::warning('Manager dashboard accessed without hotel_id', [
                'manager_id' => $manager->id,
                'manager_email' => $manager->email,
            ]);
            return response()->json([
                'kpis' => [
                    'occupancyPct' => 0,
                    'roomsAvailable' => 0,
                    'activeReservationsToday' => 0,
                    'upcomingCheckins' => 0,
                ],
                'bookingTrends' => [],
                'revenueTrends' => [],
                'alertsOpen' => 0,
            ]);
        }

        $today = Carbon::today();
        
        // Calculate occupancy metrics based on room status to match receptionist pages
        $roomsCount = Room::where('hotel_id', $hotelId)->count();
        $occupiedRoomsToday = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::OCCUPIED)
            ->count();
        $occupancyPct = $roomsCount > 0 ? round(($occupiedRoomsToday / $roomsCount) * 100, 2) : 0;

        // Get active reservations for today (for activeReservationsToday metric)
        $activeStatuses = ['confirmed', 'checked_in', 'checked_out'];
        $todayReservations = Reservation::with('room')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>=', $today)
            ->whereIn('status', $activeStatuses)
            ->get();

        $bookingsTrend = $this->buildBookingsTrend($hotelId, 6);
        $revenueTrend = $this->buildRevenueTrend($hotelId, 6);

        $alertsCount = Alert::where('hotel_id', $hotelId)->where('status', 'open')->count();

        return response()->json([
            'kpis' => [
                'occupancyPct' => $occupancyPct,
                'roomsAvailable' => Room::where('hotel_id', $hotelId)
                    ->where('status', RoomStatus::AVAILABLE)
                    ->count(),
                'activeReservationsToday' => $todayReservations->count(),
                'upcomingCheckins' => Reservation::whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
                    ->whereDate('check_in', $today)
                    ->count(),
            ],
            'bookingTrends' => $bookingsTrend,
            'revenueTrends' => $revenueTrend,
            'alertsOpen' => $alertsCount,
        ]);
    }

    private function buildBookingsTrend(int $hotelId, int $months): array
    {
        $now = Carbon::now();
        $start = $now->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $rows = Reservation::selectRaw('strftime("%Y-%m", created_at) as month, count(*) as total')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereDate('created_at', '>=', $start)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $trend = [];
        for ($i = 0; $i < $months; $i++) {
            $label = $start->copy()->addMonths($i)->format('Y-m');
            $trend[] = [
                'month' => $label,
                'bookings' => (int) ($rows[$label]->total ?? 0),
            ];
        }

        return $trend;
    }

    private function buildRevenueTrend(int $hotelId, int $months): array
    {
        $now = Carbon::now();
        $start = $now->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $rows = Reservation::with('room')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereDate('created_at', '>=', $start)
            ->get()
            ->groupBy(fn ($res) => Carbon::parse($res->created_at)->format('Y-m'))
            ->map(function ($group) {
                return $group->reduce(function ($carry, $res) {
                    $price = $res->room->price ?? 0;
                    $nights = max(1, Carbon::parse($res->check_in)->diffInDays(Carbon::parse($res->check_out)));
                    return $carry + ($price * $nights);
                }, 0);
            });

        $trend = [];
        for ($i = 0; $i < $months; $i++) {
            $label = $start->copy()->addMonths($i)->format('Y-m');
            $trend[] = [
                'month' => $label,
                'revenue' => round((float) ($rows[$label] ?? 0), 2),
            ];
        }

        return $trend;
    }
}

