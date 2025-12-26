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
        Schema::create('reservation_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('room_type');
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('nights');
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('ETB');
            $table->string('status')->default('pending'); // pending, confirmed, failed, expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_intents');
    }
};
