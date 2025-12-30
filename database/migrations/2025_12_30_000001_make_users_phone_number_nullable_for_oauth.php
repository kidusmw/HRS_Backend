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

        $driver = DB::getDriverName();

        // Keep the intended max length (20) but allow NULL so OAuth-created users can exist
        // until they complete their profile. Reservation flows still enforce phone presence.
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE `users` MODIFY `phone_number` VARCHAR(20) NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number TYPE VARCHAR(20)');
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number DROP NOT NULL');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number VARCHAR(20) NULL');
            return;
        }

        // SQLite: altering column nullability/length reliably requires table rebuild.
        // Keep validation-level enforcement for dev environments.
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'phone_number')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE `users` MODIFY `phone_number` VARCHAR(20) NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number SET NOT NULL');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE users ALTER COLUMN phone_number VARCHAR(20) NOT NULL');
            return;
        }
    }
};


