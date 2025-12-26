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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('reservation_intent_id')->nullable()->after('reservation_id')->constrained('reservation_intents')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->change();
            $table->string('transaction_reference')->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['reservation_intent_id']);
            $table->dropColumn('reservation_intent_id');
            $table->string('transaction_reference')->change();
        });
    }
};
