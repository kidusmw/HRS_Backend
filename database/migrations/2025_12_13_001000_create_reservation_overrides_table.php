<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->string('new_status'); // align with reservations.status
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('reservation_id');
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_overrides');
    }
};

