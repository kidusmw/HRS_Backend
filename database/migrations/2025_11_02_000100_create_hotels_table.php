<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->string('timezone')->default(config('app.timezone'));
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        // Add hotel_id to users (nullable)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'hotel_id')) {
                $table->foreignId('hotel_id')->nullable()->after('role')->constrained('hotels')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'hotel_id')) {
                $table->dropConstrainedForeignId('hotel_id');
            }
        });
        Schema::dropIfExists('hotels');
    }
};


