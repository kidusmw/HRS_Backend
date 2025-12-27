<?php

namespace App\Http\Controllers\Api\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Enums\RoomStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AvailabilityController extends Controller
{
    /**
     * Returns dates that should be disabled for selecting a check-in date for the given room type.
     * Rule: a check-in date is enabled if there exists at least one AVAILABLE room of that type
     * that can accommodate a minimum stay of 1 night (check_out = check_in + 1 day),
     * using the inclusive overlap rule:
     *   existing.check_in <= requested.check_out AND existing.check_out >= requested.check_in
     */
    public function checkInDates(Request $request): JsonResponse
    {
        $user = $request->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json(['message' => 'User is not associated with a hotel'], 400);
        }

        $validator = Validator::make($request->all(), [
            'room_type' => 'required|string',
            'start' => 'required|date',
            'days' => 'nullable|integer|min:1|max:180',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $roomType = (string) $request->input('room_type');
        $start = Carbon::parse($request->input('start'))->startOfDay();
        $days = (int) ($request->input('days', 90));

        $roomIds = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::AVAILABLE)
            ->where('type', $roomType)
            ->pluck('id')
            ->all();

        if (count($roomIds) === 0) {
            // No rooms of this type are available at all -> disable every day in horizon.
            return response()->json([
                'data' => $this->expandDateRange($start, $days),
            ]);
        }

        // We test 1-night stays: [d, d+1]
        $windowStart = $start->toDateString();
        $windowEnd = $start->copy()->addDays($days + 1)->toDateString();

        $reservationsByRoom = $this->loadActiveReservationsByRoom($roomIds, $windowStart, $windowEnd);

        $disabled = [];
        for ($i = 0; $i < $days; $i++) {
            $checkIn = $start->copy()->addDays($i)->toDateString();
            $checkOut = $start->copy()->addDays($i + 1)->toDateString();

            if (!$this->hasAnyRoomFreeForRange($roomIds, $reservationsByRoom, $checkIn, $checkOut)) {
                $disabled[] = $checkIn;
            }
        }

        return response()->json(['data' => $disabled]);
    }

    /**
     * Returns dates that should be disabled for selecting a check-out date for the given room type and check-in.
     * A check-out date is enabled if there exists at least one AVAILABLE room of that type that is free for the
     * full requested range [check_in, check_out] using the inclusive overlap rule.
     */
    public function checkOutDates(Request $request): JsonResponse
    {
        $user = $request->user();
        $hotelId = $user->hotel_id;

        if (!$hotelId) {
            return response()->json(['message' => 'User is not associated with a hotel'], 400);
        }

        $validator = Validator::make($request->all(), [
            'room_type' => 'required|string',
            'check_in' => 'required|date',
            'days' => 'nullable|integer|min:1|max:180',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $roomType = (string) $request->input('room_type');
        $checkInCarbon = Carbon::parse($request->input('check_in'))->startOfDay();
        $checkIn = $checkInCarbon->toDateString();
        $days = (int) ($request->input('days', 90));

        $roomIds = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::AVAILABLE)
            ->where('type', $roomType)
            ->pluck('id')
            ->all();

        if (count($roomIds) === 0) {
            // Disable all candidate check-out dates in horizon (check_in+1 .. check_in+days)
            return response()->json([
                'data' => $this->expandDateRange($checkInCarbon->copy()->addDay(), $days),
            ]);
        }

        $windowStart = $checkInCarbon->toDateString();
        $windowEnd = $checkInCarbon->copy()->addDays($days)->toDateString();

        $reservationsByRoom = $this->loadActiveReservationsByRoom($roomIds, $windowStart, $windowEnd);

        $disabled = [];
        for ($i = 1; $i <= $days; $i++) {
            $checkOut = $checkInCarbon->copy()->addDays($i)->toDateString();

            if (!$this->hasAnyRoomFreeForRange($roomIds, $reservationsByRoom, $checkIn, $checkOut)) {
                $disabled[] = $checkOut;
            }
        }

        return response()->json(['data' => $disabled]);
    }

    /**
     * Load active reservations for given room ids that overlap [windowStart, windowEnd] (inclusive).
     *
     * @return array<int, array<int, array{check_in:string,check_out:string}>>
     */
    private function loadActiveReservationsByRoom(array $roomIds, string $windowStart, string $windowEnd): array
    {
        $reservations = Reservation::whereIn('room_id', $roomIds)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->whereDate('check_in', '<=', $windowEnd)
            ->whereDate('check_out', '>=', $windowStart)
            ->get(['room_id', 'check_in', 'check_out']);

        $byRoom = [];
        foreach ($reservations as $r) {
            $byRoom[$r->room_id][] = [
                'check_in' => Carbon::parse($r->check_in)->toDateString(),
                'check_out' => Carbon::parse($r->check_out)->toDateString(),
            ];
        }

        return $byRoom;
    }

    /**
     * Determine if any room is free for requested [start, end] (inclusive) under the inclusive overlap rule.
     */
    private function hasAnyRoomFreeForRange(array $roomIds, array $reservationsByRoom, string $requestedStart, string $requestedEnd): bool
    {
        foreach ($roomIds as $roomId) {
            $blocked = false;
            $roomReservations = $reservationsByRoom[$roomId] ?? [];

            foreach ($roomReservations as $res) {
                // overlap iff existing.check_in <= requestedEnd AND existing.check_out >= requestedStart
                if ($res['check_in'] <= $requestedEnd && $res['check_out'] >= $requestedStart) {
                    $blocked = true;
                    break;
                }
            }

            if (!$blocked) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expand N days of dates starting at start (inclusive).
     *
     * @return string[]
     */
    private function expandDateRange(Carbon $start, int $days): array
    {
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $start->copy()->addDays($i)->toDateString();
        }
        return $dates;
    }
}


