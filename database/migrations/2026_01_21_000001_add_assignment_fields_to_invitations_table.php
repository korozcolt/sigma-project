<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('leader_user_id')
                ->nullable()
                ->after('municipality_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('coordinator_user_id')
                ->nullable()
                ->after('leader_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['leader_user_id', 'status']);
            $table->index(['coordinator_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex(['leader_user_id', 'status']);
            $table->dropIndex(['coordinator_user_id', 'status']);
            $table->dropConstrainedForeignId('leader_user_id');
            $table->dropConstrainedForeignId('coordinator_user_id');
        });
    }
};

