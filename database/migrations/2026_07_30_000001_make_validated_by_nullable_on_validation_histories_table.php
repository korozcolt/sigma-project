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
     * authenticated actor — Auth::id() is null) can write a ValidationHistory row. Only
     * nullability changes here — the existing cascadeOnDelete() behavior is preserved
     * (deleting the validating user still deletes their audit rows, matching the
     * pre-existing contract in ValidationHistoryTest); nullable and cascadeOnDelete are
     * orthogonal, a NULL validated_by simply has nothing to cascade from.
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
            $table->foreign('validated_by')->references('id')->on('users')->cascadeOnDelete();
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
