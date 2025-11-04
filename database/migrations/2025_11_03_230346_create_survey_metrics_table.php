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
        Schema::create('survey_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_question_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric_type'); // overall, question_distribution, question_average
            $table->integer('total_responses')->default(0);
            $table->decimal('response_rate', 5, 2)->nullable(); // Percentage
            $table->decimal('average_value', 10, 2)->nullable(); // For scale questions
            $table->json('distribution')->nullable(); // For choice and yes/no questions
            $table->json('metadata')->nullable(); // Additional stats
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['survey_id', 'metric_type']);
            $table->index(['survey_question_id', 'metric_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_metrics');
    }
};
