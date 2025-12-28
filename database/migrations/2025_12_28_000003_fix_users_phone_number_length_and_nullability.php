<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'phone_number')) {
            return;
        }

        // Fail fast if any rows are missing phone numbers (NOT NULL requirement).
        $missingCount = DB::table('users')
            ->whereNull('phone_number')
            ->orWhere('phone_number', '=', '')
            ->count();

        if ($missingCount > 0) {
            throw new RuntimeException("Cannot make users.phone_number NOT NULL: {$missingCount} user(s) have a NULL/empty phone_number.");
        }

        // Fail fast if any values exceed the intended max length.
        $tooLongCount = DB::table('users')
            ->whereRaw('CHAR_LENGTH(phone_number) > 20')
            ->count();

        if ($tooLongCount > 0) {
            throw new RuntimeException("Cannot shrink users.phone_number to VARCHAR(20): {$tooLongCount} user(s) have phone_number longer than 20 characters.");
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE `users` MODIFY `phone_number` VARCHAR(20) NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number TYPE VARCHAR(20)');
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number SET NOT NULL');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number VARCHAR(20) NOT NULL');
            return;
        }

        // SQLite: altering column nullability/length reliably requires table rebuild.
        // Keep validation-level enforcement for SQLite dev environments.
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'phone_number')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE `users` MODIFY `phone_number` VARCHAR(255) NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number DROP NOT NULL');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number VARCHAR(255) NULL');
            return;
        }
    }
};


