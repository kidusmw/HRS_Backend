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
        // Note: Since payment_status is a string column, we don't need to alter the column type
        // The enum change is handled at the application level (PaymentStatus enum)
        // We just need to update any existing data to match the new enum values
        
        // Map old values to new values
        \DB::table('reservations')->where('payment_status', 'unpaid')->update(['payment_status' => 'pending']);
        \DB::table('reservations')->where('payment_status', 'partial')->update(['payment_status' => 'pending']);
        \DB::table('reservations')->where('payment_status', 'overpaid')->update(['payment_status' => 'paid']);
        // 'paid' stays as 'paid'
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse mapping (if needed for rollback)
        \DB::table('reservations')->where('payment_status', 'pending')->update(['payment_status' => 'unpaid']);
        \DB::table('reservations')->where('payment_status', 'paid')->update(['payment_status' => 'paid']);
    }
};
