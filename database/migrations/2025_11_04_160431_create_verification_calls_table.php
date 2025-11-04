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
        Schema::create('verification_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('call_assignments')->nullOnDelete();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->timestamp('call_date')->useCurrent();
            $table->unsignedInteger('call_duration')->default(0)->comment('Duration in seconds');
            $table->string('call_result');
            $table->text('notes')->nullable();
            $table->foreignId('survey_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('survey_completed')->default(false);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();

            $table->index(['voter_id', 'call_date']);
            $table->index(['caller_id', 'call_date']);
            $table->index(['call_result']);
            $table->index(['assignment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_calls');
    }
};
