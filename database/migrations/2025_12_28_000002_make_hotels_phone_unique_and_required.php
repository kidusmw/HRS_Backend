<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fail fast if existing data violates the new constraints.
        $nullOrEmpty = DB::table('hotels')
            ->whereNull('phone')
            ->orWhere('phone', '')
            ->count();

        if ($nullOrEmpty > 0) {
            throw new RuntimeException('Cannot enforce hotels.phone NOT NULL + UNIQUE: found hotels with NULL/empty phone.');
        }

        $duplicates = DB::table('hotels')
            ->select('phone', DB::raw('COUNT(*) as c'))
            ->groupBy('phone')
            ->having('c', '>', 1)
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException('Cannot enforce hotels.phone UNIQUE: found duplicate phone values.');
        }

        $driver = Schema::getConnection()->getDriverName();

        // 1) Ensure NOT NULL when supported.
        // SQLite cannot reliably ALTER COLUMN to set NOT NULL without table rebuild; enforce via validation there.
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE hotels MODIFY phone VARCHAR(255) NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE hotels ALTER COLUMN phone SET NOT NULL");
        } elseif ($driver === 'sqlsrv') {
            // Best-effort; SQL Server requires knowing the current type length. Defaulting to NVARCHAR(255).
            DB::statement("ALTER TABLE hotels ALTER COLUMN phone NVARCHAR(255) NOT NULL");
        }

        // 2) Add unique index (works across drivers).
        Schema::table('hotels', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE hotels MODIFY phone VARCHAR(255) NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE hotels ALTER COLUMN phone DROP NOT NULL");
        } elseif ($driver === 'sqlsrv') {
            DB::statement("ALTER TABLE hotels ALTER COLUMN phone NVARCHAR(255) NULL");
        }
        // SQLite: no-op (nullable is the original behavior; table rebuild would be needed).
    }
};


