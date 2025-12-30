<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('image_url');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['hotel_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_images');
    }
};


