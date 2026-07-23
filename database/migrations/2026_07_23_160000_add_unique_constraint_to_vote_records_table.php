<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('vote_records')
            ->select('voter_id', 'election_event_id', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('election_event_id')
            ->groupBy('voter_id', 'election_event_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('vote_records')
                ->where('voter_id', $duplicate->voter_id)
                ->where('election_event_id', $duplicate->election_event_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('vote_records', function (Blueprint $table) {
            $table->unique(['voter_id', 'election_event_id'], 'vote_records_voter_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vote_records', function (Blueprint $table) {
            $table->dropUnique('vote_records_voter_event_unique');
        });
    }
};
