<?php

namespace App\Actions\Payments\Chapa;

use App\DTO\Payments\Chapa\CreateReservationIntentDto;
use App\Enums\RoomStatus;
use App\Exceptions\Payments\ReservationAlreadyPaidException;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationIntent;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateReservationIntentAction
{
    /**
     * @throws \Exception
     */
    public function execute(CreateReservationIntentDto $dto, int $userId): ReservationIntent
    {
        $hotel = Hotel::findOrFail($dto->hotelId);
        $checkIn = Carbon::parse($dto->checkIn);
        $checkOut = Carbon::parse($dto->checkOut);

        if ($checkIn->isPast() || $checkIn->isToday()) {
            throw new \InvalidArgumentException('Check-in date must be in the future');
        }

        if ($checkOut->lte($checkIn)) {
            throw new \InvalidArgumentException('Check-out date must be after check-in date');
        }

        $nights = max(1, $checkIn->diffInDays($checkOut));

        // Validate availability (without blocking)
        $availableRooms = Room::where('hotel_id', $dto->hotelId)
            ->where('type', $dto->roomType)
            ->where('status', RoomStatus::AVAILABLE)
            ->get();

        // Check for overlapping reservations
        $blockedRoomIds = Reservation::whereHas('room', function ($q) use ($dto) {
            $q->where('hotel_id', $dto->hotelId)
                ->where('type', $dto->roomType);
        })
        ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
        ->where(function ($q) use ($checkIn, $checkOut) {
            $q->where(function ($sub) use ($checkIn, $checkOut) {
                $sub->whereBetween('check_in', [$checkIn->toDateString(), $checkOut->toDateString()]);
            })
            ->orWhere(function ($sub) use ($checkIn, $checkOut) {
                $sub->whereBetween('check_out', [$checkIn->toDateString(), $checkOut->toDateString()]);
            })
            ->orWhere(function ($sub) use ($checkIn, $checkOut) {
                $sub->where('check_in', '<=', $checkIn->toDateString())
                    ->where('check_out', '>=', $checkOut->toDateString());
            });
        })
        ->pluck('room_id')
        ->unique();

        $trulyAvailable = $availableRooms->reject(fn ($room) => $blockedRoomIds->contains($room->id));

        if ($trulyAvailable->isEmpty()) {
            throw new \Exception('No rooms available for the selected dates and room type');
        }

        // Calculate price (use first available room's price * nights)
        $roomPrice = $trulyAvailable->first()->price;
        $totalAmount = (float) ($roomPrice * $nights);

        return DB::transaction(function () use ($dto, $userId, $hotel, $checkIn, $checkOut, $nights, $totalAmount) {
            return ReservationIntent::create([
                'user_id' => $userId,
                'hotel_id' => $dto->hotelId,
                'room_type' => $dto->roomType,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'nights' => $nights,
                'total_amount' => $totalAmount,
                'currency' => 'ETB',
                'status' => 'pending',
                'expires_at' => now()->addHours(24), // Intent expires in 24 hours
            ]);
        });
    }
}

