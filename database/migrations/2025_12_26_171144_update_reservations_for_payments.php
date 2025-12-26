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
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            // Allowed: pending, paid, failed, refunded
            $table->string('payment_status')->default('pending');
        });

        // Drop legacy payment_method column (source of truth is payments.method)
        if (Schema::hasColumn('reservations', 'payment_method')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add payment_method for rollback
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'payment_method')) {
                $table->string('payment_method')->nullable();
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('reservations', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('reservations', 'guest_phone')) {
                $table->dropColumn('guest_phone');
            }
            if (Schema::hasColumn('reservations', 'guest_email')) {
                $table->dropColumn('guest_email');
            }
            if (Schema::hasColumn('reservations', 'guest_name')) {
                $table->dropColumn('guest_name');
            }
        });
    }
};
