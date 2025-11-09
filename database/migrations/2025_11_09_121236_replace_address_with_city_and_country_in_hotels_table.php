<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            // Drop the address column
            $table->dropColumn('address');
            
            // Add city and country columns (non-nullable)
            $table->string('city')->after('name');
            $table->string('country')->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            // Drop city and country
            $table->dropColumn(['city', 'country']);
            
            // Restore address column
            $table->string('address')->nullable()->after('name');
        });
    }
};
