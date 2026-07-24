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
        Schema::table('voters', function (Blueprint $table) {
            $table->string('polling_place_source')->nullable()->after('polling_table_number');
            $table->timestamp('polling_place_resolved_at')->nullable()->after('polling_place_source');

            $table->index('polling_place_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropColumn(['polling_place_source', 'polling_place_resolved_at']);
        });
    }
};
