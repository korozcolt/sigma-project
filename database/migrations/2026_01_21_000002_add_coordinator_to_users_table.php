<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('coordinator_user_id')
                ->nullable()
                ->after('municipality_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('coordinator_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['coordinator_user_id']);
            $table->dropConstrainedForeignId('coordinator_user_id');
        });
    }
};

