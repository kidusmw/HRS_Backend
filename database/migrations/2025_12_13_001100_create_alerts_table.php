<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('type'); // system, hotel, overbooking, payment, maintenance
            $table->string('severity'); // info, warning, critical
            $table->text('message');
            $table->string('status')->default('open'); // open, acknowledged, resolved
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['hotel_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};

