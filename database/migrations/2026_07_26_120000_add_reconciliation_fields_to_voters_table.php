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
        Schema::table('voters', function (Blueprint $table) {
            $table->unsignedInteger('reconciliation_attempts')->default(0)->after('polling_place_resolved_at');
            $table->timestamp('reconciliation_exhausted_at')->nullable()->after('reconciliation_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropColumn(['reconciliation_attempts', 'reconciliation_exhausted_at']);
        });
    }
};
