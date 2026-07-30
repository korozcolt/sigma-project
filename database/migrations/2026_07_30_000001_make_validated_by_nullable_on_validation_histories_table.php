<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * validated_by must be nullable so the headless census revalidation job (no
     * authenticated actor — Auth::id() is null) can write a ValidationHistory row, mirroring
     * the same nullable + nullOnDelete precedent already established for
     * polling_place_resolutions.resolved_by (Phase 7 D-05 / Phase 11 D-04).
     */
    public function up(): void
    {
        Schema::table('validation_histories', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
        });

        Schema::table('validation_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('validated_by')->nullable()->change();
        });

        Schema::table('validation_histories', function (Blueprint $table) {
            $table->foreign('validated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validation_histories', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
        });

        Schema::table('validation_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('validated_by')->nullable(false)->change();
        });

        Schema::table('validation_histories', function (Blueprint $table) {
            $table->foreign('validated_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
