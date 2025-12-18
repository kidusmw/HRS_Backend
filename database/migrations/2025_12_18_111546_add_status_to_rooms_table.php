<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('status')->default('available')->after('is_available');
        });

        // Migrate existing data: convert is_available boolean to status string
        // If is_available is true -> 'available'
        // If is_available is false -> check if occupied, otherwise 'unavailable'
        // Note: This migration runs before is_available is dropped, so we can still use it
        $rooms = \DB::table('rooms')->get();
        foreach ($rooms as $room) {
            $status = 'available';
            if (!$room->is_available) {
                // Check if room has active checked-in reservation
                $hasActiveReservation = \DB::table('reservations')
                    ->where('room_id', $room->id)
                    ->where('status', 'checked_in')
                    ->where('check_out', '>=', now()->toDateString())
                    ->exists();
                
                $status = $hasActiveReservation ? 'occupied' : 'unavailable';
            }
            
            \DB::table('rooms')
                ->where('id', $room->id)
                ->update(['status' => $status]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
