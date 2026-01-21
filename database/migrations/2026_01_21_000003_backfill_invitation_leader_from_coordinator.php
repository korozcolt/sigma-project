<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invitations')
            ->whereNull('leader_user_id')
            ->whereNotNull('coordinator_user_id')
            ->update([
                'leader_user_id' => DB::raw('coordinator_user_id'),
            ]);
    }

    public function down(): void
    {
        //
    }
};

