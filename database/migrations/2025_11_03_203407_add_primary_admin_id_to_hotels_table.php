<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Enums\UserRole;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->foreignId('primary_admin_id')
                ->nullable()
                ->after('logo_path')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Populate primary_admin_id from existing admin users
        // This finds the first admin user per hotel and assigns them as primary_admin
        $hotels = DB::table('hotels')->get();
        foreach ($hotels as $hotel) {
            $admin = DB::table('users')
                ->where('hotel_id', $hotel->id)
                ->where('role', UserRole::ADMIN->value)
                ->first();
            
            if ($admin) {
                DB::table('hotels')
                    ->where('id', $hotel->id)
                    ->update(['primary_admin_id' => $admin->id]);
            }
        }

        // Note: SQLite doesn't support check constraints, but we validate in application layer
        // For PostgreSQL/MySQL, you could add:
        // DB::statement('ALTER TABLE hotels ADD CONSTRAINT check_primary_admin_role CHECK (
        //     primary_admin_id IS NULL OR 
        //     EXISTS (SELECT 1 FROM users WHERE users.id = hotels.primary_admin_id AND users.role = ?)
        // )', [UserRole::ADMIN->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropForeign(['primary_admin_id']);
            $table->dropColumn('primary_admin_id');
        });
    }
};
