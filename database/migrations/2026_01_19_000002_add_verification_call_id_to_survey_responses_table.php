<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->foreignId('verification_call_id')
                ->nullable()
                ->after('answered_by')
                ->constrained('verification_calls')
                ->nullOnDelete();

            $table->index(['verification_call_id', 'survey_question_id']);
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropForeign(['verification_call_id']);
            $table->dropColumn('verification_call_id');
        });
    }
};

