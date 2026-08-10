<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('area_coordinator_user_id')
                ->nullable()
                ->after('coordinator_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('area_coordinator_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['area_coordinator_user_id']);
            $table->dropConstrainedForeignId('area_coordinator_user_id');
        });
    }
};
