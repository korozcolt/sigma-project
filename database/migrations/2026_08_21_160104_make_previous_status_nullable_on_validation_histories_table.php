<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * previous_status must be nullable so ValidationHistorySankeyChart (VIZ-07) can
     * represent a voter's initial registration as a synthetic "Nuevo" source node
     * (23-CONTEXT.md D-06) — the writer side of this column already stores VoterStatus
     * values only, but no row currently records a "no prior status" initial-creation
     * event, and the schema itself blocked ever doing so. Mirrors the existing
     * validated_by-nullable migration's pattern on this same table.
     */
    public function up(): void
    {
        Schema::table('validation_histories', function (Blueprint $table) {
            $table->string('previous_status')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validation_histories', function (Blueprint $table) {
            $table->string('previous_status')->nullable(false)->change();
        });
    }
};
