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
        Schema::create('validation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voter_id')->constrained()->cascadeOnDelete();
            $table->string('previous_status');
            $table->string('new_status');
            $table->foreignId('validated_by')->constrained('users')->cascadeOnDelete();
            $table->string('validation_type');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('voter_id');
            $table->index('validation_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('validation_histories');
    }
};
