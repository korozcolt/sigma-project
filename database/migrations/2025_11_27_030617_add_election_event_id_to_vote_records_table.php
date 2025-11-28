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
        Schema::table('vote_records', function (Blueprint $table) {
            $table->foreignId('election_event_id')
                ->after('campaign_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->index(['election_event_id', 'voted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vote_records', function (Blueprint $table) {
            $table->dropForeign(['election_event_id']);
            $table->dropIndex(['election_event_id', 'voted_at']);
            $table->dropColumn('election_event_id');
        });
    }
};
